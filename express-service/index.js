/**
 * Microservicio Express - Registro de ventas.
 * Conecta a MongoDB (configurar MONGODB_URI y MONGODB_DB en .env).
 */
require("dotenv").config();
const express = require("express");
const app = express();

app.use(express.json());

const PORT = process.env.PORT || 3000;

app.get("/health", (req, res) => {
  res.json({ status: "ok" });
});

app.listen(PORT, () => {
  console.log(`Express en http://127.0.0.1:${PORT}`);
});
