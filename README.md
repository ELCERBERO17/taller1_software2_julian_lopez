# taller1_software2_julian_lopez

Sistema de ventas con microservicios: API Gateway (Laravel), inventario (Flask + Firebase), ventas (Express + MongoDB).

---

## Requisitos previos (instalar en una máquina nueva)

| Herramienta | Versión | Descarga |
|-------------|---------|----------|
| Git | Última | https://git-scm.com/download/win |
| Node.js | LTS | https://nodejs.org/ |
| Python | 3.11 o 3.12 | https://www.python.org/downloads/ (marcar "Add to PATH") |
| PHP | 8.2+ | XAMPP https://www.apachefriends.org/ o https://windows.php.net/download/ |
| Composer | Última | https://getcomposer.org/download/ |
| MongoDB | 6.x o 7.x | https://www.mongodb.com/try/download/community |
| Postman | - | https://www.postman.com/downloads/ (opcional) |

Versiones usadas en desarrollo: Python 3.13.7, Laravel 12.x, Composer 2.9.5, XAMPP 8.2 (PHP 8.2.12), MongoDB 8.2.5.

Necesitas también: cuenta Firebase, proyecto Firestore y archivo JSON de credenciales (Service Account). Colocar el JSON en la carpeta `environments/` del proyecto.

---

## Estructura del proyecto

```
taller1_software2_julian_lopez/
├── gateway/           # Laravel (API Gateway)
├── flask-service/     # Flask + Firebase (productos/inventario)
├── express-service/   # Express + MongoDB (ventas)
├── database/mongodb/  # Script seed para MongoDB
├── diagram/           # Diagramas del sistema
├── environments/      # Credenciales Firebase (no subir a Git)
└── README.md
```

---

## 1. Clonar e instalar dependencias

```bash
git clone <url-repositorio>
cd taller1_software2_julian_lopez
```

### Gateway (Laravel)
```bash
cd gateway
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate
cd ..
```

### Flask (inventario)
```bash
cd flask-service
python -m venv venv
venv\Scripts\activate
pip install -r requirements.txt
cd ..
```

### Express (ventas)
```bash
cd express-service
npm install
cd ..
```

### Seed MongoDB (opcional; si no, la colección se crea con la primera venta)
```bash
cd database/mongodb
npm install
npm run seed
cd ../..
```

---

## 2. Variables de entorno y tokens

Crear o completar el `.env` en cada servicio. No subir `.env` ni credenciales al repositorio. El valor de `GATEWAY_INTERNAL_TOKEN` debe ser exactamente el mismo en los tres servicios.

### gateway/.env
Añadir (además de lo que trae Laravel):

```
FLASK_SERVICE_URL=http://127.0.0.1:5001
EXPRESS_SERVICE_URL=http://127.0.0.1:3000
GATEWAY_INTERNAL_TOKEN=<un_mismo_token_largo_y_seguro>
```

`JWT_SECRET` se genera con `php artisan jwt:secret`.

### flask-service/.env
Crear desde `flask-service/.env.example`:

```
FLASK_PORT=5001
GATEWAY_INTERNAL_TOKEN=<el_mismo_valor_que_en_gateway>
FIREBASE_CREDENTIALS=../environments/<nombre-del-archivo-credenciales>.json
```

El archivo de credenciales va en la carpeta `environments/` (por ejemplo `software2-xxxx-firebase-adminsdk-xxxx.json`).

### express-service/.env
Crear desde `express-service/.env.example`:

```
PORT=3000
MONGODB_URI=mongodb://localhost:27017
MONGODB_DB=ventas_db
GATEWAY_INTERNAL_TOKEN=<el_mismo_valor_que_en_gateway>
```

---

## 3. Bases de datos

### Laravel (usuarios)
Se crea con `php artisan migrate` (paso 1). Usa SQLite por defecto.

### Firebase Firestore (productos)
En Firebase Console → Firestore → Reglas, pegar:

```
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    match /{document=**} {
      allow read, write: if request.time < timestamp.date(2026, 4, 13);
    }
  }
}
```

La colección `productos` se crea al iniciar Flask; se insertan 3 productos (Laptop, Smartphone, Audífonos inalámbricos) con stock 100 cada uno.

### MongoDB (ventas)
Base de datos: `ventas_db`. Colección: `ventas` (campos: `producto_id`, `cantidad`, `usuario_id`, `fecha`, `total`). Crear con `npm run seed` en `database/mongodb` o al registrar la primera venta.

### Usuario de prueba
En la carpeta `gateway`:

```bash
php artisan tinker
```

Dentro de Tinker (una línea):

```php
User::create(['name'=>'Test','email'=>'user@example.com','password'=>bcrypt('password')]);
exit
```

---

## 4. Orden para iniciar los servicios

1. **MongoDB:** iniciar el servicio (Windows: `net start MongoDB`) o en una terminal `mongod`.
2. **Flask:** `cd flask-service`, `venv\Scripts\activate`, `python app.py` → http://127.0.0.1:5001
3. **Express:** `cd express-service`, `npm start` → http://127.0.0.1:3000
4. **Gateway:** `cd gateway`, `php artisan serve` → http://127.0.0.1:8000

Comprobar: http://127.0.0.1:5001/health , http://127.0.0.1:3000/health , http://127.0.0.1:8000

---

## 5. Endpoints por el Gateway

Base URL: **http://127.0.0.1:8000/api**

Todas las rutas protegidas requieren el header: `Authorization: Bearer <token>` (token devuelto por POST /login).

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | /login | Login; retorna JWT |
| POST | /logout | Cerrar sesión |
| GET | /user | Usuario autenticado |
| GET | /productos | Lista de productos |
| GET | /productos/{id}/stock | Stock de un producto |
| POST | /ventas | Registrar venta |
| GET | /ventas | Listar ventas |
| GET | /ventas/fecha/{fecha} | Ventas por fecha (YYYY-MM-DD) |
| GET | /ventas/usuario/{usuario} | Ventas por usuario (ID) |

### POST /login
Body (JSON):
```json
{"email": "user@example.com", "password": "password"}
```
Respuesta 200: `{"token": "...", "user": {...}, "type": "bearer"}`

### POST /ventas
Body (JSON):
```json
{"producto_id": "prod001", "cantidad": 2}
```
IDs de producto: prod001 (Laptop), prod002 (Smartphone), prod003 (Audífonos inalámbricos).

### Códigos de respuesta al registrar venta
| HTTP | Código | Significado |
|------|--------|-------------|
| 201 | SALE_SUCCESS | Venta registrada |
| 400 | INSUFFICIENT_STOCK | No hay stock suficiente |
| 404 | PRODUCT_NOT_FOUND | Producto no existe |
| 401 | - | Token JWT inválido |
| 422 | - | Datos inválidos (producto_id o cantidad) |
| 502 | SALE_REGISTER_FAILED | Fallo al conectar con Express (revisar MongoDB y token interno) |
| 502 | INVENTORY_ERROR | Fallo al conectar con Flask (revisar Firebase y token interno) |

---

## 6. Flujo de registro de una venta

1. El cliente envía **POST /api/ventas** con JWT en el header `Authorization: Bearer <token>` y body `{"producto_id", "cantidad"}`.
2. El Gateway valida el JWT del usuario.
3. El Gateway llama al microservicio de inventario (Flask) para **verificar stock** del producto.
4. Si el producto no existe o no hay stock suficiente, el Gateway responde **404** (producto no existe) o **400** (sin stock).
5. Si hay stock, el Gateway llama al microservicio de ventas (Express) para **registrar la venta** en MongoDB.
6. El Gateway llama a Flask para **actualizar el inventario** (descontar la cantidad vendida).
7. El Gateway responde **201** con los datos de la venta.

Los diagramas están en la carpeta `diagram/`.

---

## 7. Pruebas en Postman

Orden sugerido: Login → Listar productos → Verificar stock (prod001) → Registrar venta (prod001, 2) → Listar ventas → Logout.

- **Login:** POST http://127.0.0.1:8000/api/login , Body raw JSON: `{"email":"user@example.com","password":"password"}`. Copiar el `token` de la respuesta.
- **Productos:** GET http://127.0.0.1:8000/api/productos , Header: `Authorization: Bearer <token>`.
- **Stock:** GET http://127.0.0.1:8000/api/productos/prod001/stock , Header: `Authorization: Bearer <token>`.
- **Registrar venta:** POST http://127.0.0.1:8000/api/ventas , Header: `Authorization: Bearer <token>`, Body: `{"producto_id":"prod001","cantidad":2}`.
- **Listar ventas:** GET http://127.0.0.1:8000/api/ventas , Header: `Authorization: Bearer <token>`.
- **Ventas por fecha:** GET http://127.0.0.1:8000/api/ventas/fecha/2025-03-15.
- **Ventas por usuario:** GET http://127.0.0.1:8000/api/ventas/usuario/1.
- **Logout:** POST http://127.0.0.1:8000/api/logout , Header: `Authorization: Bearer <token>`.

Para probar errores: producto inexistente `prod999` (404), cantidad mayor al stock (400).
