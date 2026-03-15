<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class VentaController extends Controller
{
    private string $flaskUrl;
    private string $expressUrl;
    private string $gatewayToken;

    public function __construct()
    {
        $this->flaskUrl = rtrim(config('services.flask.url', env('FLASK_SERVICE_URL', 'http://127.0.0.1:5001')), '/');
        $this->expressUrl = rtrim(config('services.express.url', env('EXPRESS_SERVICE_URL', 'http://127.0.0.1:3000')), '/');
        $this->gatewayToken = config('services.gateway_token', env('GATEWAY_INTERNAL_TOKEN', 'gateway-secret-token'));
    }

    private function headerToken(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->gatewayToken,
            'X-Gateway-Token' => $this->gatewayToken,
        ];
    }

    /**
     * Registrar venta.
     * Flujo: 1) Verificar stock en Flask  2) Registrar en Express  3) Actualizar inventario en Flask
     *
     * Códigos de respuesta:
     * - 201: Venta registrada
     * - 400: No hay stock
     * - 404: Producto no existe
     * - 422: Datos inválidos
     */
    public function registrar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'producto_id' => 'required|string',
            'cantidad' => 'required|integer|min:1',
        ]);

        $productoId = $validated['producto_id'];
        $cantidad = (int) $validated['cantidad'];
        $usuarioId = (string) auth('api')->id();

        // 1. Verificar stock en Flask
        $stockResponse = Http::withHeaders($this->headerToken())
            ->get("{$this->flaskUrl}/productos/{$productoId}/stock");

        if (!$stockResponse->successful()) {
            if ($stockResponse->status() === 401) {
                return response()->json(['error' => 'Token de microservicio inválido', 'code' => 'GATEWAY_AUTH_FAILED'], 502);
            }
            return response()->json(['error' => 'Error al consultar inventario', 'code' => 'INVENTORY_ERROR'], 502);
        }

        $data = $stockResponse->json();

        // Producto no existe
        if (($data['disponible'] ?? 0) === 0) {
            if (str_contains(strtolower($data['mensaje'] ?? ''), 'no existe')) {
                return response()->json([
                    'error' => 'Producto no existe',
                    'code' => 'PRODUCT_NOT_FOUND',
                    'producto_id' => $productoId,
                ], 404);
            }
            // No hay stock
            return response()->json([
                'error' => 'No hay stock disponible',
                'code' => 'INSUFFICIENT_STOCK',
                'producto_id' => $productoId,
                'stock_actual' => $data['stock_actual'] ?? 0,
            ], 400);
        }

        $stockActual = (int) ($data['stock_actual'] ?? 0);
        if ($cantidad > $stockActual) {
            return response()->json([
                'error' => 'Cantidad solicitada excede el stock',
                'code' => 'INSUFFICIENT_STOCK',
                'producto_id' => $productoId,
                'stock_actual' => $stockActual,
                'cantidad_solicitada' => $cantidad,
            ], 400);
        }

        // 2. Registrar venta en Express
        $ventaResponse = Http::withHeaders($this->headerToken())
            ->post("{$this->expressUrl}/ventas", [
                'producto_id' => $productoId,
                'cantidad' => $cantidad,
                'usuario_id' => $usuarioId,
            ]);

        if (!$ventaResponse->successful()) {
            return response()->json([
                'error' => 'Error al registrar venta',
                'code' => 'SALE_REGISTER_FAILED',
            ], 502);
        }

        // 3. Actualizar inventario en Flask
        Http::withHeaders($this->headerToken())
            ->patch("{$this->flaskUrl}/productos/{$productoId}/actualizar-inventario", [
                'cantidad_vendida' => $cantidad,
            ]);

        return response()->json([
            'message' => 'Venta registrada',
            'code' => 'SALE_SUCCESS',
            'venta' => $ventaResponse->json(),
        ], 201);
    }
}
