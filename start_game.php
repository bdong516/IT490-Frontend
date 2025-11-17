<?php
// Enable error visibility for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Debug start
file_put_contents("/var/www/html/start_game_debug.txt", "===== START =====\n", FILE_APPEND);

// Read raw JSON input
$raw = file_get_contents("php://input");
file_put_contents("/var/www/html/start_game_debug.txt", "RAW: $raw\n", FILE_APPEND);

$data = json_decode($raw, true);

// Validate payload
if (!$data || !isset($data["Flag"]) || $data["Flag"] !== "start_game") {
    file_put_contents("/var/www/html/start_game_debug.txt", "Invalid payload\n", FILE_APPEND);
    echo json_encode(["success" => false, "message" => "Invalid payload"]);
    exit;
}

try {
    // Connect to RabbitMQ
    $conn = new AMQPStreamConnection(
        $RABBITMQ_HOST,
        $RABBITMQ_PORT,
        $USERNAME,
        $PASSWORD
    );

    $channel = $conn->channel();
    $channel->queue_declare($QUEUE_NAME, false, true, false, false);

    file_put_contents("/var/www/html/start_game_debug.txt", "Publishing to MQ\n", FILE_APPEND);

    // Publish message to Backend2
    $msg = new AMQPMessage($raw);
    $channel->basic_publish($msg, '', $QUEUE_NAME);

    $channel->close();
    $conn->close();

    file_put_contents("/var/www/html/start_game_debug.txt", "SUCCESS\n", FILE_APPEND);

    echo json_encode(["success" => true, "message" => "Game started"]);

} catch (Exception $e) {

    $err = "RabbitMQ ERROR: " . $e->getMessage() . "\n";
    file_put_contents("/var/www/html/start_game_debug.txt", $err, FILE_APPEND);

    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
