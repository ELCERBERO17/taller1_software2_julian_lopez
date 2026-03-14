"""
Microservicio Flask - Gestión de productos e inventario.
Conecta a Firebase (configurar FIREBASE_CREDENTIALS en .env).
"""
import os
from flask import Flask, request, jsonify
from dotenv import load_dotenv

load_dotenv()

app = Flask(__name__)
PORT = int(os.getenv("FLASK_PORT", 5001))


@app.route("/health", methods=["GET"])
def health():
    """Verificar que el servicio está activo."""
    return jsonify({"status": "ok"}), 200


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=PORT, debug=True)
