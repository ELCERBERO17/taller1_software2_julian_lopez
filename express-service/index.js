/**
 * Microservicio Express - Registro de ventas.
 * Conecta a MongoDB. Valida token del Gateway.
 *
 * Códigos de respuesta:
 * - 200/201: OK
 * - 400: Datos inválidos
 * - 401: Token inválido (no proviene del Gateway)
 */
require("dotenv").config();
const express = require("express");
const mongoose = require("mongoose");

const app = express();
app.use(express.json());

const PORT = process.env.PORT || 3000;
const MONGODB_URI = process.env.MONGODB_URI || "mongodb://localhost:27017";
const MONGODB_DB = process.env.MONGODB_DB || "ventas_db";
const GATEWAY_TOKEN = process.env.GATEWAY_INTERNAL_TOKEN || "gateway-secret-token";

const requireGatewayToken = (req, res, next) => {
  const auth = req.headers.authorization || req.headers["x-gateway-token"];
  const token = auth && auth.startsWith("Bearer ") ? auth.slice(7) : auth || "";
  if (token !== GATEWAY_TOKEN) {
    return res.status(401).json({ error: "Unauthorized", code: "INVALID_TOKEN" });
  }
  next();
};

const ventaSchema = new mongoose.Schema(
  {
    producto_id: { type: String, required: true },
    cantidad: { type: Number, required: true },
    usuario_id: { type: String, required: true },
    fecha: { type: Date, default: Date.now },
    total: { type: Number },
  },
  { collection: "ventas" }
);
const Venta = mongoose.model("Venta", ventaSchema);

app.get("/health", (req, res) => res.json({ status: "ok" }));

app.post("/ventas", requireGatewayToken, async (req, res) => {
  try {
    const { producto_id, cantidad, usuario_id, total } = req.body;
    if (!producto_id || !cantidad || !usuario_id) {
      return res.status(400).json({
        error: "Datos incompletos",
        code: "INVALID_DATA",
        required: ["producto_id", "cantidad", "usuario_id"],
      });
    }
    const venta = new Venta({
      producto_id,
      cantidad: Number(cantidad),
      usuario_id,
      total: total || 0,
    });
    await venta.save();
    return res.status(201).json({
      id: venta._id,
      producto_id,
      cantidad: venta.cantidad,
      usuario_id,
      fecha: venta.fecha,
      total: venta.total,
    });
  } catch (err) {
    return res.status(500).json({ error: "Error interno", code: "INTERNAL_ERROR" });
  }
});

app.get("/ventas", requireGatewayToken, async (req, res) => {
  try {
    const ventas = await Venta.find().sort({ fecha: -1 }).lean();
    return res.json({ ventas });
  } catch (err) {
    return res.status(500).json({ error: "Error interno", code: "INTERNAL_ERROR" });
  }
});

app.get("/ventas/fecha/:fecha", requireGatewayToken, async (req, res) => {
  try {
    const fecha = req.params.fecha;
    const inicio = new Date(fecha);
    const fin = new Date(fecha);
    fin.setDate(fin.getDate() + 1);
    const ventas = await Venta.find({ fecha: { $gte: inicio, $lt: fin } }).lean();
    return res.json({ ventas });
  } catch (err) {
    return res.status(500).json({ error: "Error interno", code: "INTERNAL_ERROR" });
  }
});

app.get("/ventas/usuario/:usuario", requireGatewayToken, async (req, res) => {
  try {
    const ventas = await Venta.find({ usuario_id: req.params.usuario }).sort({ fecha: -1 }).lean();
    return res.json({ ventas });
  } catch (err) {
    return res.status(500).json({ error: "Error interno", code: "INTERNAL_ERROR" });
  }
});

async function start() {
  try {
    await mongoose.connect(MONGODB_URI, { dbName: MONGODB_DB });
    console.log("MongoDB conectado:", MONGODB_DB);
  } catch (err) {
    console.error("Error conectando a MongoDB:", err.message);
  }
  app.listen(PORT, () => {
    console.log(`Express en http://127.0.0.1:${PORT}`);
  });
}

start();
