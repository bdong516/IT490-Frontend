#!/usr/bin/env python3
import pika
import json
import sys
import time
import os
import ssl
from dotenv import load_dotenv

# Load environment
load_dotenv("/home/bd293/cinemadle_env/.env")

HAPROXY_HOST = os.getenv("RABBIT_HOST")
PORT_TLS     = int(os.getenv("RABBIT_TLS_PORT", "5671"))
USERNAME     = os.getenv("RABBIT_USERNAME")
PASSWORD     = os.getenv("RABBIT_PASSWORD")

QUEUE_NAME   = os.getenv("QUEUE_BACKEND2_TO_FRONT")  # should be BACKEND2_TO_FRONT

OUTPUT_FILE  = os.getenv("RESPONSE_FILE", "/var/www/html/response_status.json")

sys.stdout.reconfigure(line_buffering=True)

def get_rabbitmq_connection():
    print(f"\n[TLS] Connecting to {HAPROXY_HOST}:{PORT_TLS} ...")

    ssl_context = ssl.SSLContext(ssl.PROTOCOL_TLS_CLIENT)
    ssl_context.check_hostname = False
    ssl_context.verify_mode = ssl.CERT_NONE

    credentials = pika.PlainCredentials(USERNAME, PASSWORD)

    params = pika.ConnectionParameters(
        host=HAPROXY_HOST,
        port=PORT_TLS,
        credentials=credentials,
        heartbeat=600,
        blocked_connection_timeout=300,
        ssl_options=pika.SSLOptions(ssl_context)
    )

    connection = pika.BlockingConnection(params)
    channel = connection.channel()

    # MUST MATCH EXACTLY OR IT WON'T RECEIVE ANYTHING
    channel.queue_declare(queue=QUEUE_NAME, durable=True)

    print(f"[TLS] Connected. Listening to queue: {QUEUE_NAME}")
    return connection, channel


def callback(ch, method, properties, body):
    try:
        message = json.loads(body.decode())
        message["Timestamp"] = time.time()

        tmp = OUTPUT_FILE + ".tmp"
        with open(tmp, "w") as f:
            json.dump(message, f, indent=4)
            f.flush()
            os.fsync(f.fileno())

        os.replace(tmp, OUTPUT_FILE)
        print(f"[OK] Wrote response for Flag={message.get('Flag')} SessionID={message.get('SessionID')}")

    except Exception as e:
        print("[ERROR] Failed to process message:", e)
        import traceback
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

            print("[WAITING] Awaiting messages...")
            channel.start_consuming()

        except KeyboardInterrupt:
            print("Shutting down listener...")
            try:
                connection.close()
            except:
                pass
            break

        except Exception as e:
            print("\n[ERROR] Lost connection:", e)
            print("[RETRY] Reconnecting in 5 seconds...\n")
            time.sleep(5)
