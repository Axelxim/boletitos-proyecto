<?php

namespace App\Services;

use App\Models\BitacoraModel;

class BitacoraService
{
    public function __construct(private readonly BitacoraModel $bitacoraModel = new BitacoraModel())
    {
    }

    public function registrar(array $data): bool
    {
        $payload = [
            'usuario_id'       => $data['usuario_id'] ?? null,
            'modulo'           => sanitize_simple_string($data['modulo'] ?? ''),
            'accion'           => sanitize_simple_string($data['accion'] ?? ''),
            'descripcion'      => sanitize_simple_string($data['descripcion'] ?? ''),
            'ip'               => $data['ip'] ?? client_ip_address(),
            'user_agent'       => $data['user_agent'] ?? client_user_agent(),
            'datos_anteriores' => $this->encodeJson($data['datos_anteriores'] ?? null),
            'datos_nuevos'     => $this->encodeJson($data['datos_nuevos'] ?? null),
            'created_at'       => $data['created_at'] ?? app_timestamp(),
        ];

        return $this->bitacoraModel->insert($payload) !== false;
    }

    private function encodeJson(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
