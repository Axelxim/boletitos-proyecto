<?php

namespace App\Services;

use App\Models\SedeModel;

class SedeService
{
    public function __construct(
        private readonly SedeModel $sedeModel = new SedeModel(),
        private readonly BitacoraService $bitacoraService = new BitacoraService()
    ) {
    }

    public function obtenerTodas(): array
    {
        return $this->sedeModel
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        $sede = $this->sedeModel->find($id);

        return $sede ?: null;
    }

    public function crear(array $data): ?array
    {
        $payload = $this->prepararPayload($data, false);
        $inserted = $this->sedeModel->insert($payload);

        if ($inserted === false) {
            return null;
        }

        $sede = $this->obtenerPorId((int) $inserted);

        $this->bitacoraService->registrar([
            'usuario_id'   => get_authenticated_user()['id'] ?? null,
            'modulo'       => 'sedes',
            'accion'       => 'create',
            'descripcion'  => 'Sede creada correctamente',
            'datos_nuevos' => $sede,
        ]);

        return $sede;
    }

    public function actualizar(int $id, array $data): ?array
    {
        $sedeActual = $this->obtenerPorId($id);

        if ($sedeActual === null) {
            return null;
        }

        $payload = $this->prepararPayload($data, true);
        $updated = $this->sedeModel->update($id, $payload);

        if ($updated === false) {
            return null;
        }

        $sedeNueva = $this->obtenerPorId($id);

        $this->bitacoraService->registrar([
            'usuario_id'       => get_authenticated_user()['id'] ?? null,
            'modulo'           => 'sedes',
            'accion'           => 'update',
            'descripcion'      => 'Sede actualizada correctamente',
            'datos_anteriores' => $sedeActual,
            'datos_nuevos'     => $sedeNueva,
        ]);

        return $sedeNueva;
    }

    public function eliminar(int $id): bool
    {
        $sedeActual = $this->obtenerPorId($id);

        if ($sedeActual === null) {
            return false;
        }

        $deleted = $this->sedeModel->delete($id);

        if ($deleted === false) {
            return false;
        }

        $this->bitacoraService->registrar([
            'usuario_id'       => get_authenticated_user()['id'] ?? null,
            'modulo'           => 'sedes',
            'accion'           => 'delete',
            'descripcion'      => 'Sede eliminada correctamente',
            'datos_anteriores' => $sedeActual,
        ]);

        return true;
    }

    private function prepararPayload(array $data, bool $isUpdate): array
    {
        $payload = [
            'nombre'      => sanitize_simple_string($data['nombre'] ?? ''),
            'direccion'   => sanitize_simple_string($data['direccion'] ?? ''),
            'ciudad'      => sanitize_simple_string($data['ciudad'] ?? ''),
            'estado'      => sanitize_simple_string($data['estado'] ?? ''),
            'pais'        => sanitize_simple_string($data['pais'] ?? ''),
            'latitud'     => sanitize_simple_string($data['latitud'] ?? ''),
            'longitud'    => sanitize_simple_string($data['longitud'] ?? ''),
            'url_mapa'    => sanitize_simple_string($data['url_mapa'] ?? ''),
            'capacidad'   => $data['capacidad'] ?? null,
            'estado_sede' => sanitize_simple_string($data['estado_sede'] ?? 'activo'),
            'updated_at'  => app_timestamp(),
        ];

        if (! $isUpdate) {
            $payload['created_at'] = app_timestamp();
        }

        return $payload;
    }
}
