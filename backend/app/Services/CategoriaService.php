<?php

namespace App\Services;

use App\Models\CategoriaModel;

class CategoriaService
{
    public function __construct(
        private readonly CategoriaModel $categoriaModel = new CategoriaModel(),
        private readonly BitacoraService $bitacoraService = new BitacoraService()
    ) {
    }

    public function obtenerTodas(): array
    {
        return $this->categoriaModel
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        $categoria = $this->categoriaModel->find($id);

        return $categoria ?: null;
    }

    public function crear(array $data): ?array
    {
        $payload = $this->prepararPayload($data, false);
        $inserted = $this->categoriaModel->insert($payload);

        if ($inserted === false) {
            return null;
        }

        $categoria = $this->obtenerPorId((int) $inserted);

        $this->bitacoraService->registrar([
            'usuario_id'   => get_authenticated_user()['id'] ?? null,
            'modulo'       => 'categorias',
            'accion'       => 'create',
            'descripcion'  => 'Categoria creada correctamente',
            'datos_nuevos' => $categoria,
        ]);

        return $categoria;
    }

    public function actualizar(int $id, array $data): ?array
    {
        $categoriaActual = $this->obtenerPorId($id);

        if ($categoriaActual === null) {
            return null;
        }

        $payload = $this->prepararPayload($data, true);
        $updated = $this->categoriaModel->update($id, $payload);

        if ($updated === false) {
            return null;
        }

        $categoriaNueva = $this->obtenerPorId($id);

        $this->bitacoraService->registrar([
            'usuario_id'       => get_authenticated_user()['id'] ?? null,
            'modulo'           => 'categorias',
            'accion'           => 'update',
            'descripcion'      => 'Categoria actualizada correctamente',
            'datos_anteriores' => $categoriaActual,
            'datos_nuevos'     => $categoriaNueva,
        ]);

        return $categoriaNueva;
    }

    public function eliminar(int $id): bool
    {
        $categoriaActual = $this->obtenerPorId($id);

        if ($categoriaActual === null) {
            return false;
        }

        $deleted = $this->categoriaModel->delete($id);

        if ($deleted === false) {
            return false;
        }

        $this->bitacoraService->registrar([
            'usuario_id'       => get_authenticated_user()['id'] ?? null,
            'modulo'           => 'categorias',
            'accion'           => 'delete',
            'descripcion'      => 'Categoria eliminada correctamente',
            'datos_anteriores' => $categoriaActual,
        ]);

        return true;
    }

    private function prepararPayload(array $data, bool $isUpdate): array
    {
        $payload = [
            'nombre'      => sanitize_simple_string($data['nombre'] ?? ''),
            'descripcion' => sanitize_simple_string($data['descripcion'] ?? ''),
            'estado'      => sanitize_simple_string($data['estado'] ?? 'activo'),
            'updated_at'  => app_timestamp(),
        ];

        if (! $isUpdate) {
            $payload['created_at'] = app_timestamp();
        }

        return $payload;
    }
}
