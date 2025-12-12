<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
session_start();

$HAPROXY_HOST = '100.86.66.48';
$PORT_PLAIN   = 5672;
$PORT_TLS     = 5671;
$USE_TLS      = false;  // PHP uses non-TLS, Python uses TLS
$USERNAME     = 'jol';
$PASSWORD     = 'sysadmin';
$QUEUE_NAME   = 'FRONTEND_TO_BACKEND2';
$responseFile = '/var/www/html/response_status.json';

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || ($data['Flag'] ?? '') !== 'guess') {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$sessionID = $data['Payload']['SessionID'] ?? null;
if (!$sessionID) {
    echo json_encode(['success' => false, 'message' => 'Missing SessionID']);
    exit;
}

$beforeMtime = file_exists($responseFile) ? filemtime($responseFile) : 0;

try {
    if ($USE_TLS) {
        $conn = new AMQPStreamConnection(
            $HAPROXY_HOST,
            $PORT_TLS,
            $USERNAME,
            $PASSWORD,
            '/',
            false,
            'AMQPLAIN',
            null,
            'en_US',
            3,
            3,
            null,
            true,
            [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            ]
        );
    } else {
        $conn = new AMQPStreamConnection(
            $HAPROXY_HOST,
            $PORT_PLAIN,
            $USERNAME,
            $PASSWORD
        );
    }

    $channel = $conn->channel();
    $channel->queue_declare($QUEUE_NAME, false, true, false, false);

    $msg = new AMQPMessage($raw);
    $channel->basic_publish($msg, '', $QUEUE_NAME);

    $channel->close();
    $conn->close();
} catch (Exception $e) {
    error_log('RabbitMQ TLS/AMQP connection error (send_guess.php): ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'RabbitMQ TLS connection failed']);
    exit;
}

$timeout  = 6;
$start    = microtime(true);
$response = null;

while (microtime(true) - $start < $timeout) {
    clearstatcache(true, $responseFile);

    if (file_exists($responseFile)) {
        $mtime = filemtime($responseFile);

        if ($mtime > $beforeMtime) {
            $json = json_decode(file_get_contents($responseFile), true);

            if (
                $json &&
                isset($json['SessionID']) &&
                $json['SessionID'] === $sessionID &&
                isset($json['Flag']) &&
                (str_starts_with($json['Flag'], 'guess_') ||
                 str_starts_with($json['Flag'], 'game_'))
            ) {
                $response = $json;
                break;
            }
        }
    }

    usleep(15000);
}

if (!$response) {
    echo json_encode(['success' => false, 'message' => 'No response from backend (timeout)']);
    exit;
}

echo json_encode([
    'success' => true,
    'data'    => $response,
]);
