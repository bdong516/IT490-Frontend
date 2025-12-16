<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Dotenv\Dotenv;

session_start();

$dotenv = Dotenv::createImmutable('/home/bd293/cinemadle_env');
$dotenv->load();

$HAPROXY_HOST = $_ENV['HAPROXY_HOST'] ?? '100.86.66.48';
$PORT_PLAIN   = (int)($_ENV['RABBITMQ_PORT'] ?? 5672);
$USERNAME     = $_ENV['RABBITMQ_USERNAME'] ?? 'jol';
$PASSWORD     = $_ENV['RABBITMQ_PASSWORD'] ?? 'sysadmin';
$QUEUE_NAME   = $_ENV['QUEUE_FRONTEND_TO_BACKEND1'] ?? 'FRONTEND_TO_BACKEND1';

$responseFile = $_ENV['RESPONSE_FILE'] ?? '/var/www/html/response_status.json';

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['Flag'])) {
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
    error_log('RabbitMQ error (send_user_data.php): ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'RabbitMQ connection failed']);
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
            $beforeMtime = $mtime; // 🔑 CRITICAL FIX

            $json = json_decode(file_get_contents($responseFile), true);

            if ($json && isset($json['Flag'])) {
                if (in_array($json['Flag'], [
                    'Login_Accepted',
                    'Login_Denied',
                    'Register_Allowed',
                    'Register_Failed'
                ])) {
                    $response = $json;
                    break;
                }
            }
        }
    }

    usleep(15000);
}

if (!$response) {
    echo json_encode(['success' => false, 'message' => 'No backend response']);
    exit;
}

switch ($response['Flag']) {
    case 'Login_Accepted':
        $_SESSION['logged_in']  = true;
        $_SESSION['username']   = $response['Username'];
        $_SESSION['session_id'] = $response['SessionID'] ?? null;

        echo json_encode([
            'success'  => true,
            'message'  => $response['Message'] ?? 'Login successful!',
            'username' => $response['Username'],
        ]);
        break;

    case 'Login_Denied':
        echo json_encode([
            'success' => false,
            'message' => 'Invalid username or password',
        ]);
        break;

    case 'Register_Allowed':
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful!',
        ]);
        break;

    case 'Register_Failed':
        echo json_encode([
            'success' => false,
            'message' => 'Registration failed',
        ]);
        break;

    default:
        echo json_encode([
            'success' => false,
            'message' => 'Unexpected backend flag',
        ]);
}
