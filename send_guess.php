<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

session_start();

// RabbitMQ backend2 queue
$RABBITMQ_HOST = '100.113.228.22';
$RABBITMQ_PORT = 5672;
$USERNAME = 'jol';
$PASSWORD = 'sysadmin';
$QUEUE_NAME = 'FRONTEND_TO_BACKEND2';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

// Basic validation
if (!$data || ($data['Flag'] ?? '') !== 'guess') {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$sessionID = $data['Payload']['SessionID'] ?? null;
if (!$sessionID) {
    echo json_encode(['success' => false, 'message' => 'Missing SessionID']);
    exit;
}

$responseFile = '/var/www/html/response_status.json';

// 1) Record file modification time BEFORE sending the guess
$beforeMtime = 0;
if (file_exists($responseFile)) {
    $beforeMtime = filemtime($responseFile);
}

// 2) Publish guess to backend2
try {
    $conn = new AMQPStreamConnection(
        $RABBITMQ_HOST,
        $RABBITMQ_PORT,
        $USERNAME,
        $PASSWORD
    );
    $channel = $conn->channel();
    $channel->queue_declare($QUEUE_NAME, false, true, false, false);

    $msg = new AMQPMessage($raw);
    $channel->basic_publish($msg, '', $QUEUE_NAME);

    $channel->close();
    $conn->close();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'RabbitMQ error: ' . $e->getMessage()
    ]);
    exit;
}

// 3) Poll for NEW response from backend2
$timeout = 6;  // seconds
$start = microtime(true);

while (microtime(true) - $start < $timeout) {
    // Clear any cached file metadata
    clearstatcache(true, $responseFile);

    if (file_exists($responseFile)) {
        $currentMtime = filemtime($responseFile);

        // Only read if file was modified AFTER we sent the guess
        if ($currentMtime > $beforeMtime) {
            $json = json_decode(file_get_contents($responseFile), true);

            if ($json &&
                isset($json['SessionID']) &&
                $json['SessionID'] === $sessionID &&
                isset($json['Flag']) &&
                (str_starts_with($json['Flag'], 'guess_') ||
                 str_starts_with($json['Flag'], 'game_'))
            ) {
                echo json_encode([
                    'success' => true,
                    'data' => $json
                ]);
                exit;
            }
            // If file is newer but doesn’t match this session/flag,
            // keep looping for our response.
        }
    }

    usleep(20000); // 20ms
}

// 4) Timed out waiting for a fresh, matching response
echo json_encode([
    'success' => false,
    'message' => 'No response from backend (timeout)'
]);
?>
