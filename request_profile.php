<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
session_start();

$HAPROXY_HOST = '100.86.66.48';
$PORT_PLAIN   = 5672;
$USERNAME     = 'jol';
$PASSWORD     = 'sysadmin';
$QUEUE_NAME   = 'FRONTEND_TO_BACKEND2';
$responseFile = '/var/www/html/response_status.json';

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['Flag']) || $data['Flag'] !== 'request_user_profile') {
    echo json_encode(['success' => false, 'message' => 'Invalid request payload']);
    exit;
}

$beforeMtime = file_exists($responseFile) ? filemtime($responseFile) : 0;

try {
    $connection = new AMQPStreamConnection(
        $HAPROXY_HOST,
        $PORT_PLAIN,
        $USERNAME,
        $PASSWORD
    );

    $channel = $connection->channel();
    $channel->queue_declare($QUEUE_NAME, false, true, false, false);

    $msg = new AMQPMessage($raw);
    $channel->basic_publish($msg, '', $QUEUE_NAME);

    $channel->close();
    $connection->close();
} catch (Exception $e) {
    $errorMsg = $e->getMessage();
    error_log('RabbitMQ connection error (request_profile.php): ' . $errorMsg);
    echo json_encode(['success' => false, 'message' => 'RabbitMQ connection failed: ' . $errorMsg]);
    exit;
}

// Wait for response
$timeout  = 8;
$start    = microtime(true);
$response = null;

while (microtime(true) - $start < $timeout) {
    clearstatcache(true, $responseFile);

    if (file_exists($responseFile)) {
        $mtime = filemtime($responseFile);
        if ($mtime > $beforeMtime) {
            $json = json_decode(file_get_contents($responseFile), true);
            if ($json && isset($json['Flag'])) {
                // Look for user profile responses
                if (in_array($json['Flag'], [
                    'user_profile_request_ack',
                    'user_profile_data',
                    'user_profile_error'
                ])) {
                    // If we get ACK, keep waiting for actual data
                    if ($json['Flag'] === 'user_profile_request_ack') {
                        $beforeMtime = $mtime;
                        continue;
                    }
                    
                    $response = $json;
                    break;
                }
            }
        }
    }
    usleep(250000); // 100ms
}

if (!$response) {
    echo json_encode(['success' => false, 'message' => 'No backend response']);
    exit;
}

if ($response['Flag'] === 'user_profile_data') {
    echo json_encode([
        'success' => true,
        'data' => $response
    ]);
} else if ($response['Flag'] === 'user_profile_error') {
    echo json_encode([
        'success' => false,
        'message' => $response['Message'] ?? 'Failed to load profile',
        'data' => $response
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Unexpected backend response'
    ]);
}
