import mysql.connector
from dotenv import load_dotenv
import os

load_dotenv()

def get_connection():
    return mysql.connector.connect(
        host=os.getenv("DB_HOST", "localhost"),
        user=os.getenv("DB_USER", "root"),
        password=os.getenv("DB_PASSWORD", "2634"),
        database=os.getenv("DB_NAME", "dietapp"),
        port=int(os.getenv("DB_PORT", "3306"))
    )
