#!/usr/bin/env python3
import pika
import json
import sys
import time
import os
import ssl
from dotenv import load_dotenv

# -----------------------------------
# Load environment variables
# -----------------------------------
load_dotenv("/home/bd293/cinemadle_env/.env")

HAPROXY_HOST = os.getenv("HAPROXY_HOST")
PORT_TLS     = int(os.getenv("RABBITMQ_PORT_TLS", "5671"))
USERNAME     = os.getenv("RABBITMQ_USERNAME")
PASSWORD     = os.getenv("RABBITMQ_PASSWORD")
QUEUE_NAME   = os.getenv("QUEUE_BACKEND2_TO_FRONT")
OUTPUT_FILE  = os.getenv("RESPONSE_FILE", "/var/www/html/response_status.json")

sys.stdout.reconfigure(line_buffering=True)

# -----------------------------------
# TLS Connection Factory
# -----------------------------------
def get_rabbitmq_connection():
    print(f"[TLS] Connecting to {HAPROXY_HOST}:{PORT_TLS}")

    ssl_context = ssl.SSLContext(ssl.PROTOCOL_TLS_CLIENT)
    ssl_context.check_hostname = False
    ssl_context.verify_mode = ssl.CERT_NONE  # Allow self-signed certs

    credentials = pika.PlainCredentials(USERNAME, PASSWORD)

    params = pika.ConnectionParameters(
        host=HAPROXY_HOST,
        port=PORT_TLS,
        credentials=credentials,
        ssl_options=pika.SSLOptions(ssl_context),
        heartbeat=600,
        blocked_connection_timeout=300
    )

    connection = pika.BlockingConnection(params)
    channel = connection.channel()
    channel.queue_declare(queue=QUEUE_NAME, durable=True)

    print("[TLS] Connected successfully.")
    return connection, channel

# -----------------------------------
# Handle incoming backend2 messages
# -----------------------------------
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
        print(f"[OK] Wrote response for SessionID={message.get('SessionID')}")

    except Exception as e:
        print("[ERROR] Failed to process message:", e)
        import traceback
        traceback.print_exc()


# -----------------------------------
# Main listener loop with auto-reconnect
# -----------------------------------
if __name__ == "__main__":
    while True:
        try:
            connection, channel = get_rabbitmq_connection()

            channel.basic_consume(
                queue=QUEUE_NAME,
                on_message_callback=callback,
                auto_ack=True
            )

            print(f"[WAITING] Listening on queue: {QUEUE_NAME}")
            channel.start_consuming()

        except pika.exceptions.AMQPConnectionError:
            print("[WARN] Lost connection. Reconnecting in 5 seconds...")
            time.sleep(5)

        except KeyboardInterrupt:
            print("\n[EXIT] Shutting down listener.")
            try:
                connection.close()
            except:
                pass
            break

        except Exception as e:
            print("[ERROR] Unexpected exception:", e)
            import traceback
            traceback.print_exc()
            time.sleep(5)
