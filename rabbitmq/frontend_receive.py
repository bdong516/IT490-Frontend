#!/usr/bin/env python3
import pika
import json
import sys
import time
import os
import traceback

sys.stdout.reconfigure(line_buffering=True)

HAPROXY_HOST = "100.86.66.48"
USERNAME = "jol"
PASSWORD = "sysadmin"
PORT = 5672
QUEUE_NAME = "BACKEND2_TO_FRONT"

OUTPUT_FILE = "/var/www/html/response_status.json"

def get_rabbitmq_connection():
    credentials = pika.PlainCredentials(USERNAME, PASSWORD)
    params = pika.ConnectionParameters(
        host=HAPROXY_HOST,
        port=PORT,
        credentials=credentials,
        heartbeat=600,
        blocked_connection_timeout=300
    )
    connection = pika.BlockingConnection(params)
    channel = connection.channel()
    channel.queue_declare(queue=QUEUE_NAME, durable=True)
    return connection, channel

def callback(ch, method, properties, body):
    try:
        message = json.loads(body.decode())
        message["Timestamp"] = time.time()

        temp_path = OUTPUT_FILE + ".tmp"
        with open(temp_path, "w") as f:
            json.dump(message, f, indent=4)
            f.flush()
            os.fsync(f.fileno())

        os.replace(temp_path, OUTPUT_FILE)

    except Exception:
        traceback.print_exc()

if __name__ == "__main__":
    while True:
        try:
            connection, channel = get_rabbitmq_connection()

            channel.basic_consume(
                queue=QUEUE_NAME,
                on_message_callback=callback,
                auto_ack=True
            )

            channel.start_consuming()

        except pika.exceptions.AMQPConnectionError:
            time.sleep(5)

        except KeyboardInterrupt:
            try:
                connection.close()
            except:
                pass
            break

        except Exception:
            traceback.print_exc()
            time.sleep(5)
