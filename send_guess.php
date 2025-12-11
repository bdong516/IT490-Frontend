<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Dotenv\Dotenv;

session_start();

/* Load .env */
$dotenv = Dotenv::createImmutable('/home/bd293/cinemadle_env');
$dotenv->load();

/* RabbitMQ settings (TLS always on) */
$HAPROXY_HOST = $_ENV["HAPROXY_HOST"];
$PORT         = intval($_ENV["RABBITMQ_PORT_TLS"]);
$USERNAME     = $_ENV["RABBITMQ_USERNAME"];
$PASSWORD     = $_ENV["RABBITMQ_PASSWORD"];
$QUEUE_NAME   = $_ENV["QUEUE_FRONTEND_TO_BACKEND2"];
$responseFile = $_ENV["RESPONSE_FILE"];

/* Read request */
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

/* Send guess over TLS */
try {
    $connection = new AMQPStreamConnection(
        $HAPROXY_HOST,
        $PORT,
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
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    );

    $channel = $connection->channel();
    $channel->queue_declare($QUEUE_NAME, false, true, false, false);

    $msg = new AMQPMessage($raw);
    $channel->basic_publish($msg, '', $QUEUE_NAME);

    $channel->close();
    $connection->close();

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "RabbitMQ TLS connection failed"]);
    exit;
}

/* Wait for backend2 guess response */
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
    echo json_encode(["success" => false, "message" => "No response from backend"]);
    exit;
}

echo json_encode([
    "success" => true,
    "data" => $response
]);
?>
