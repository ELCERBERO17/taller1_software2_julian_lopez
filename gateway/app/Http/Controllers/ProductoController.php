<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ProductoController extends Controller
{
    private string $flaskUrl;
    private string $gatewayToken;

    public function __construct()
    {
        $this->flaskUrl = rtrim(env('FLASK_SERVICE_URL', 'http://127.0.0.1:5001'), '/');
        $this->gatewayToken = env('GATEWAY_INTERNAL_TOKEN', 'gateway-secret-token');
    }

    private function headerToken(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->gatewayToken,
            'X-Gateway-Token' => $this->gatewayToken,
        ];
    }

    public function listar(): JsonResponse
    {
        $response = Http::withHeaders($this->headerToken())->get("{$this->flaskUrl}/productos");
        return response()->json($response->json(), $response->status());
    }

    public function verificarStock(string $id): JsonResponse
    {
        $response = Http::withHeaders($this->headerToken())->get("{$this->flaskUrl}/productos/{$id}/stock");
        return response()->json($response->json(), $response->status());
    }
}
