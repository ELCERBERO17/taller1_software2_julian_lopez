"""
Microservicio Flask - Gestión de productos e inventario.
Conecta a Firebase Firestore. Stock inicial: 3 productos con 100 unidades cada uno.

Códigos de respuesta:
- 200: OK
- 400: No hay stock
- 404: Producto no existe
- 401: Token inválido (no proviene del Gateway)
"""
import os
from pathlib import Path

from flask import Flask, request, jsonify
from dotenv import load_dotenv
import firebase_admin
from firebase_admin import credentials, firestore

load_dotenv()

app = Flask(__name__)
PORT = int(os.getenv("FLASK_PORT", 5001))
GATEWAY_TOKEN = os.getenv("GATEWAY_INTERNAL_TOKEN", "gateway-secret-token")
COLLECTION = "productos"

# Inicializar Firebase
_cred_path = os.getenv("FIREBASE_CREDENTIALS")
if _cred_path:
    # Resolver ruta relativa desde flask-service
    if not os.path.isabs(_cred_path):
        _cred_path = str(Path(__file__).parent.parent / _cred_path.lstrip("./"))
    cred = credentials.Certificate(_cred_path)
    firebase_admin.initialize_app(cred)

db = firestore.client() if _cred_path else None


def _require_gateway_token():
    """Valida que la petición venga del Gateway."""
    auth = request.headers.get("Authorization") or request.headers.get("X-Gateway-Token")
    token = (auth.replace("Bearer ", "") if auth and auth.startswith("Bearer ") else auth) or ""
    if token != GATEWAY_TOKEN:
        return jsonify({"error": "Unauthorized", "code": "INVALID_TOKEN"}), 401
    return None


def _get_product_ref(product_id):
    """Obtiene referencia al producto por ID."""
    if not db:
        return None
    doc = db.collection(COLLECTION).document(product_id).get()
    return doc if doc.exists else None


def _seed_initial_products():
    """Crea 3 productos con stock 100 si no existen."""
    if not db:
        return
    products = [
        {"id": "prod001", "nombre": "Laptop", "stock": 100, "precio": 899.99},
        {"id": "prod002", "nombre": "Smartphone", "stock": 100, "precio": 499.99},
        {"id": "prod003", "nombre": "Audífonos inalámbricos", "stock": 100, "precio": 79.99},
    ]
    for p in products:
        ref = db.collection(COLLECTION).document(p["id"])
        if not ref.get().exists:
            ref.set({"nombre": p["nombre"], "stock": p["stock"], "precio": p["precio"]})


@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok"}), 200


@app.route("/productos", methods=["GET"])
def listar_productos():
    err = _require_gateway_token()
    if err:
        return err
    if not db:
        return jsonify({"productos": [], "message": "Firebase no configurado"}), 200
    _seed_initial_products()
    docs = db.collection(COLLECTION).stream()
    productos = [{"id": d.id, **d.to_dict()} for d in docs]
    return jsonify({"productos": productos}), 200


@app.route("/productos", methods=["POST"])
def crear_producto():
    err = _require_gateway_token()
    if err:
        return err
    if not db:
        return jsonify({"error": "Service unavailable", "code": "NO_DB"}), 503
    data = request.get_json() or {}
    nombre = data.get("nombre")
    stock = int(data.get("stock", 0))
    precio = float(data.get("precio", 0))
    ref = db.collection(COLLECTION).document()
    ref.set({"nombre": nombre, "stock": stock, "precio": precio})
    return jsonify({"id": ref.id, "nombre": nombre, "stock": stock, "precio": precio}), 201


@app.route("/productos/<product_id>/stock", methods=["GET"])
def verificar_stock(product_id):
    """
    Retorna disponibilidad del producto.
    - 1: existe y hay stock
    - 0: no existe o no hay stock
    """
    err = _require_gateway_token()
    if err:
        return err
    if not db:
        return jsonify({"disponible": 0, "mensaje": "Producto no disponible"}), 200
    _seed_initial_products()
    doc = _get_product_ref(product_id)
    if not doc:
        return jsonify({"disponible": 0, "mensaje": "Producto no existe", "producto_id": product_id}), 200
    data = doc.to_dict()
    stock = int(data.get("stock", 0))
    disponible = 1 if stock > 0 else 0
    return jsonify({
        "disponible": disponible,
        "stock_actual": stock,
        "producto_id": product_id,
        "nombre": data.get("nombre"),
    }), 200


@app.route("/productos/<product_id>/actualizar-inventario", methods=["PATCH"])
def actualizar_inventario(product_id):
    """Actualiza stock después de una venta. Body: { "cantidad_vendida": N }"""
    err = _require_gateway_token()
    if err:
        return err
    if not db:
        return jsonify({"error": "Service unavailable", "code": "NO_DB"}), 503
    doc = _get_product_ref(product_id)
    if not doc:
        return jsonify({"error": "Producto no existe", "code": "PRODUCT_NOT_FOUND"}), 404
    data = request.get_json() or {}
    cantidad = int(data.get("cantidad_vendida", 0))
    if cantidad <= 0:
        return jsonify({"error": "Cantidad inválida", "code": "INVALID_AMOUNT"}), 400
    ref = db.collection(COLLECTION).document(product_id)
    current = ref.get().to_dict()
    stock_actual = int(current.get("stock", 0))
    if cantidad > stock_actual:
        return jsonify({"error": "Stock insuficiente", "code": "INSUFFICIENT_STOCK"}), 400
    nuevo_stock = stock_actual - cantidad
    ref.update({"stock": nuevo_stock})
    return jsonify({
        "producto_id": product_id,
        "stock_anterior": stock_actual,
        "stock_nuevo": nuevo_stock,
        "cantidad_vendida": cantidad,
    }), 200


if __name__ == "__main__":
    if db:
        _seed_initial_products()
    app.run(host="0.0.0.0", port=PORT, debug=True)
