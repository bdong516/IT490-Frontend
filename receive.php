<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;

$host = '100.90.183.2';   // RabbitMQ host
$port = 7012;          // Default port
$user = 'bd293';       // RabbitMQ username
$pass = '1958080Bd$';       // RabbitMQ password
$queue = 'Test_To_Frontend'; // The queue you’re listening to

// Connect to RabbitMQ
$connection = new AMQPStreamConnection($host, $port, $user, $pass);
$channel = $connection->channel();

// Declare the queue (make sure it exists)
$channel->queue_declare($queue, false, true, false, false);

echo " [*] Waiting for messages from RabbitMQ. To exit press CTRL+C\n";

// Callback function runs each time a message is received
$callback = function ($msg) {
    echo " [x] Received message: ", $msg->body, "\n";

    // Try decoding the JSON message
    $data = json_decode($msg->body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo " [!] Invalid JSON: ", json_last_error_msg(), "\n";
        return;
    }

    // Path to store messages
    $file = __DIR__ . '/movie_data.json';

    // Read existing messages or start with an empty array
    $messages = file_exists($file)
        ? json_decode(file_get_contents($file), true)
        : [];

    if (!is_array($messages)) {
        $messages = []; // If file was corrupted or empty
    }

    // Add the new message with a timestamp
    $messages[] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => $data
    ];

    // Save messages back to file (pretty JSON for readability)
    file_put_contents($file, json_encode($messages, JSON_PRETTY_PRINT));

    echo " [√] Saved to movie_data.json\n";
};

// Start consuming messages
$channel->basic_consume($queue, '', false, true, false, false, $callback);

// Keep listening
while ($channel->is_consuming()) {
    $channel->wait();
}

// Close channel and connection when done
$channel->close();
$connection->close();
