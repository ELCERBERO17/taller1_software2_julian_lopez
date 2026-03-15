<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class VentaQueryController extends Controller
{
    private string $expressUrl;
    private string $gatewayToken;

    public function __construct()
    {
        $this->expressUrl = rtrim(env('EXPRESS_SERVICE_URL', 'http://127.0.0.1:3000'), '/');
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
        $response = Http::withHeaders($this->headerToken())->get("{$this->expressUrl}/ventas");
        return response()->json($response->json(), $response->status());
    }

    public function porFecha(string $fecha): JsonResponse
    {
        $response = Http::withHeaders($this->headerToken())->get("{$this->expressUrl}/ventas/fecha/{$fecha}");
        return response()->json($response->json(), $response->status());
    }

    public function porUsuario(string $usuario): JsonResponse
    {
        $response = Http::withHeaders($this->headerToken())->get("{$this->expressUrl}/ventas/usuario/{$usuario}");
        return response()->json($response->json(), $response->status());
    }
}
