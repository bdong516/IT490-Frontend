<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

session_start();

$HAPROXY_HOST = '100.86.66.48';
$RABBITMQ_PORT = 5672;
$USERNAME = 'jol';
$PASSWORD = 'sysadmin';
$QUEUE_NAME = 'FRONTEND_TO_BACKEND2';

$responseFile = '/var/www/html/response_status.json';

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!$data || ($data["Flag"] ?? "") !== "guess") {
    echo json_encode(["success" => false, "message" => "Invalid input"]);
    exit;
}

$sessionID = $data["Payload"]["SessionID"] ?? null;
if (!$sessionID) {
    echo json_encode(["success" => false, "message" => "Missing SessionID"]);
    exit;
}

$beforeMtime = file_exists($responseFile) ? filemtime($responseFile) : 0;

try {
    $conn = new AMQPStreamConnection(
        $HAPROXY_HOST,
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
    echo json_encode(["success" => false, "message" => "RabbitMQ unreachable"]);
    exit;
}

$timeout = 6;
$start = microtime(true);
$response = null;

while (microtime(true) - $start < $timeout) {
    clearstatcache(true, $responseFile);

    if (file_exists($responseFile)) {
        $mtime = filemtime($responseFile);
        if ($mtime > $beforeMtime) {
            $json = json_decode(file_get_contents($responseFile), true);

            if ($json &&
                isset($json["SessionID"]) &&
                $json["SessionID"] === $sessionID &&
                isset($json["Flag"]) &&
                (str_starts_with($json["Flag"], "guess_") ||
                 str_starts_with($json["Flag"], "game_"))
            ) {
                $response = $json;
                break;
            }
        }
    }
    usleep(15000);
}

if (!$response) {
    echo json_encode(["success" => false, "message" => "No response from backend (timeout)"]);
    exit;
}

echo json_encode([
    "success" => true,
    "data" => $response
]);
?>
