<?php
session_start();

// If user isn't logged in, redirect to login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Optional: Ask Backend2 to confirm the session is still valid
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$RABBITMQ_HOST = '100.90.183.2';
$RABBITMQ_PORT = 7012;
$USERNAME = 'jol';
$PASSWORD = 'sysadmin';
$QUEUE_NAME = 'FRONTEND_TO_BACKEND1';

// Send session validation request
$data = [
    "Flag" => "Session_Validate_Request",
    "SessionID" => $_SESSION['session_id'] ?? null
];

try {
    $connection = new AMQPStreamConnection($RABBITMQ_HOST, $RABBITMQ_PORT, $USERNAME, $PASSWORD);
    $channel = $connection->channel();
    $channel->queue_declare($QUEUE_NAME, false, true, false, false);

    $msg = new AMQPMessage(json_encode($data));
    $channel->basic_publish($msg, '', $QUEUE_NAME);
    $channel->close();
    $connection->close();
} catch (Exception $e) {
    // If RabbitMQ is down, fall back to local session validity
    error_log("Session validation RabbitMQ error: " . $e->getMessage());
}

// Wait briefly for Backend2 to reply
$responseFile = '/var/www/html/response_status.json';
$timeout = 5;
$start = time();
$status = null;

while (time() - $start < $timeout) {
    if (file_exists($responseFile)) {
        $response = json_decode(file_get_contents($responseFile), true);
        if ($response && isset($response['Flag'])) {
            $status = $response['Flag'];
            break;
        }
    }
    usleep(500000);
}

// If Backend2 says invalid, destroy session
if ($status === 'Session_Invalid') {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}
?>
