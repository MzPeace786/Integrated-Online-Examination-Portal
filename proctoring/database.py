"""
database.py — MySQL database helper for the proctoring service.
"""
import os
import logging
import mysql.connector
from mysql.connector import Error
from dotenv import load_dotenv

load_dotenv()
logger = logging.getLogger(__name__)


class Database:
    def __init__(self):
        self.conn = mysql.connector.connect(
            host=os.getenv('DB_HOST', 'localhost'),
            port=int(os.getenv('DB_PORT', 3306)),
            database=os.getenv('DB_NAME', 'exam_portal'),
            user=os.getenv('DB_USER', 'root'),
            password=os.getenv('DB_PASSWORD', ''),
            autocommit=True,
            charset='utf8mb4',
            collation='utf8mb4_unicode_ci',
        )
        self.cursor = self.conn.cursor(dictionary=True)

    def fetchone(self, sql: str, params: tuple) -> dict | None:
        try:
            self.cursor.execute(sql, params)
            return self.cursor.fetchone()
        except Error as e:
            logger.error(f"DB fetchone error: {e} | SQL: {sql}")
            raise

    def fetchall(self, sql: str, params: tuple) -> list:
        try:
            self.cursor.execute(sql, params)
            return self.cursor.fetchall()
        except Error as e:
            logger.error(f"DB fetchall error: {e} | SQL: {sql}")
            raise

    def insert(self, sql: str, params: tuple) -> int:
        try:
            self.cursor.execute(sql, params)
            return self.cursor.lastrowid
        except Error as e:
            logger.error(f"DB insert error: {e} | SQL: {sql}")
            raise

    def execute(self, sql: str, params: tuple) -> int:
        try:
            self.cursor.execute(sql, params)
            return self.cursor.rowcount
        except Error as e:
            logger.error(f"DB execute error: {e} | SQL: {sql}")
            raise

    def __del__(self):
        try:
            if hasattr(self, 'cursor') and self.cursor:
                self.cursor.close()
            if hasattr(self, 'conn') and self.conn.is_connected():
                self.conn.close()
        except Exception:
            pass
