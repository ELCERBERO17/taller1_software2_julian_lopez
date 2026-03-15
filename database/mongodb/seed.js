/**
 * Seed MongoDB: crear base ventas_db y colección ventas.
 * Ejecutar: cd database/mongodb && npm install && npm run seed
 */
const { MongoClient } = require("mongodb");

const url = process.env.MONGODB_URI || "mongodb://localhost:27017";
const dbName = process.env.MONGODB_DB || "ventas_db";

async function seed() {
  const client = new MongoClient(url);
  try {
    await client.connect();
    const db = client.db(dbName);
    const collections = await db.listCollections().toArray();
    const ventasExists = collections.some((c) => c.name === "ventas");
    if (!ventasExists) {
      await db.createCollection("ventas");
      console.log("Colección 'ventas' creada.");
    }
    console.log(`Base de datos "${dbName}" lista.`);
  } finally {
    await client.close();
  }
}

seed().catch(console.error);
