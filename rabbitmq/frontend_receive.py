#!/usr/bin/env python3
import pika
import json
import sys
import time
import os

# Force immediate stdout flushing
sys.stdout.reconfigure(line_buffering=True)

RABBITMQ_HOST = "100.113.228.22"
RABBITMQ_PORT = 5672
USERNAME = "jol"
PASSWORD = "sysadmin"
QUEUE_NAME = "BACKEND2_TO_FRONT"

OUTPUT_FILE = "/var/www/html/response_status.json"


def callback(ch, method, properties, body):
    """Runs every time Backend2 sends a message."""
    try:
        message = json.loads(body.decode())

        # Add a timestamp so frontend knows it's fresh
        message["Timestamp"] = time.time()

        print("\nReceived Backend2 message:")
        print(json.dumps(message, indent=4))

        # --- ATOMIC WRITE FIX ---
        temp_path = OUTPUT_FILE + ".tmp"

        # Write to a temporary file first
        with open(temp_path, "w") as f:
            json.dump(message, f, indent=4)
            f.flush()
            os.fsync(f.fileno())  # ensure data fully written

        # Atomic replace — instant swap (no partial writes)
        os.replace(temp_path, OUTPUT_FILE)

        print("response_status.json updated atomically.\n")
        sys.stdout.flush()

    except Exception as e:
        print(f"Error while processing message: {e}")
        print(f"Raw message: {body.decode()}")
        sys.stdout.flush()


def main():
    try:
        print(f"Connecting to RabbitMQ at {RABBITMQ_HOST}:{RABBITMQ_PORT}...")
        credentials = pika.PlainCredentials(USERNAME, PASSWORD)
        params = pika.ConnectionParameters(
            host=RABBITMQ_HOST,
            port=RABBITMQ_PORT,
            credentials=credentials
        )

        connection = pika.BlockingConnection(params)
        channel = connection.channel()
        channel.queue_declare(queue=QUEUE_NAME, durable=True)

        print(f"Listening on queue '{QUEUE_NAME}'...\n")

        channel.basic_consume(
            queue=QUEUE_NAME,
            on_message_callback=callback,
            auto_ack=True
        )

        channel.start_consuming()

    except KeyboardInterrupt:
        print("Listener stopped manually.")
        sys.exit(0)

    except Exception as e:
        print(f"Fatal listener error: {e}")
        sys.exit(1)


if __name__ == "__main__":
    main()
