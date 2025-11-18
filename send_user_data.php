<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

session_start();

$RABBITMQ_HOST = '100.113.228.22';
$RABBITMQ_PORT = 5672;
$USERNAME = 'jol';
$PASSWORD = 'sysadmin';
$QUEUE_NAME = 'FRONTEND_TO_BACKEND1';

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!$data || !isset($data["Flag"])) {
    echo json_encode(["success" => false, "message" => "Invalid request"]);
    exit;
}

// Send to backend1
try {
    $conn = new AMQPStreamConnection($RABBITMQ_HOST, $RABBITMQ_PORT, $USERNAME, $PASSWORD);
    $channel = $conn->channel();
    $channel->queue_declare($QUEUE_NAME, false, true, false, false);

    $msg = new AMQPMessage($raw);
    $channel->basic_publish($msg, '', $QUEUE_NAME);

    $channel->close();
    $conn->close();
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "RabbitMQ error: ".$e->getMessage()]);
    exit;
}

// Wait for backend2
$responseFile = "/var/www/html/response_status.json";
$timeout = 6;
$start = time();
$response = null;

while (time() - $start < $timeout) {
    if (file_exists($responseFile)) {
        $payload = json_decode(file_get_contents($responseFile), true);
        if ($payload && isset($payload["Flag"])) {
            $response = $payload;
            break;
        }
    }
    usleep(200000);
}

// Interpret backend2 responses
if (!$response) {
    echo json_encode(["success" => false, "message" => "No backend response"]);
    exit;
}

switch ($response["Flag"]) {
    case "Login_Accepted":
        $_SESSION["logged_in"] = true;
        $_SESSION["username"] = $response["Username"];
        $_SESSION["session_id"] = $response["SessionID"];

        echo json_encode([
            "success" => true,
            "message" => "Login successful!",
            "username" => $response["Username"]
        ]);
        break;

    case "Login_Denied":
        echo json_encode(["success" => false, "message" => "Invalid username or password"]);
        break;

    case "Register_Allowed":
        echo json_encode(["success" => true, "message" => "Registration successful!"]);
        break;

    case "Register_Failed":
        echo json_encode(["success" => false, "message" => "Registration failed"]);
        break;

    default:
        echo json_encode(["success" => false, "message" => "Unexpected backend reply"]);
}
?>
