<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Dotenv\Dotenv;

session_start();

$dotenv = Dotenv::createImmutable('/home/bd293/cinemadle_env');
$dotenv->load();

$HAPROXY_HOST = $_ENV['RABBIT_HOST'] ?? '100.86.66.48';
$PORT_PLAIN   = isset($_ENV['RABBIT_PORT']) ? (int)$_ENV['RABBIT_PORT'] : 5672;
$PORT_TLS     = isset($_ENV['RABBIT_TLS_PORT']) ? (int)$_ENV['RABBIT_TLS_PORT'] : 5671;
$USE_TLS      = strtolower($_ENV['USE_TLS'] ?? 'false') === 'true';

$USERNAME = $_ENV['RABBIT_USERNAME'] ?? 'jol';
$PASSWORD = $_ENV['RABBIT_PASSWORD'] ?? 'sysadmin';

$QUEUE = $_ENV['QUEUE_FRONTEND_TO_BACKEND2'] ?? 'FRONTEND_TO_BACKEND2';

$responseFile = $_ENV['RESPONSE_FILE'] ?? '/var/www/html/response_status.json';

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (
    !$data ||
    !isset($data['Flag']) ||
    !in_array($data['Flag'], ['start_daily_game', 'start_random_game'])
) {
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
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
        $sslOptions = [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ];

        $conn = new AMQPSSLConnection(
            $HAPROXY_HOST,
            $PORT_TLS,
            $USERNAME,
            $PASSWORD,
            '/',
            $sslOptions
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
    $channel->queue_declare($QUEUE, false, true, false, false);

    $msg = new AMQPMessage($raw);
    $channel->basic_publish($msg, '', $QUEUE);

    $channel->close();
    $conn->close();

} catch (Exception $e) {
    error_log('RabbitMQ TLS/AMQP connection error (start_game.php): ' . $e->getMessage());
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
                in_array($json['Flag'], [
                    'daily_game_started',
                    'random_game_started',
                    'daily_already_played'
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
    echo json_encode(['success' => false, 'message' => 'No response from backend (timeout)']);
    exit;
}

echo json_encode([
    'success' => true,
    'data'    => $response,
]);
