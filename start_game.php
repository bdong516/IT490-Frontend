<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

session_start();

$HAPROXY_HOST = '100.86.66.48';
$PORT = 5672;
$USERNAME = 'jol';
$PASSWORD = 'sysadmin';
$QUEUE = 'FRONTEND_TO_BACKEND2';

$responseFile = '/var/www/html/response_status.json';

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!$data || !isset($data["Flag"]) || !in_array($data["Flag"], ["start_daily_game","start_random_game"])) {
    echo json_encode(["success"=>false,"message"=>"Invalid payload"]);
    exit;
}

$sessionID = $data["Payload"]["SessionID"] ?? null;
if (!$sessionID) {
    echo json_encode(["success"=>false,"message"=>"Missing SessionID"]);
    exit;
}

$beforeMtime = file_exists($responseFile) ? filemtime($responseFile) : 0;

try {
    $conn = new AMQPStreamConnection($HAPROXY_HOST,$PORT,$USERNAME,$PASSWORD);
    $channel = $conn->channel();
    $channel->queue_declare($QUEUE,false,true,false,false);

    $msg = new AMQPMessage($raw);
    $channel->basic_publish($msg,'',$QUEUE);

    $channel->close();
    $conn->close();
} catch (Exception $e) {
    echo json_encode(["success"=>false,"message"=>"RabbitMQ error: ".$e->getMessage()]);
    exit;
}

$timeout = 6;
$start = microtime(true);
$response = null;

while (microtime(true)-$start < $timeout) {
    clearstatcache(true,$responseFile);

    if (file_exists($responseFile)) {
        $mtime = filemtime($responseFile);

        if ($mtime > $beforeMtime) {
            $json = json_decode(file_get_contents($responseFile), true);

            if ($json &&
                isset($json["SessionID"]) &&
                $json["SessionID"] === $sessionID &&
                isset($json["Flag"]) &&
                in_array($json["Flag"], [
                    "daily_game_started",
                    "random_game_started",
                    "daily_already_played"
                ])
            ) {
                $response = $json;
                break;
            }
        }
    }

    usleep(15000);
}

if (!$response) {
    echo json_encode(["success"=>false,"message"=>"No response from backend (timeout)"]);
    exit;
}

echo json_encode([
    "success"=>true,
    "data"=>$response
]);
?>
