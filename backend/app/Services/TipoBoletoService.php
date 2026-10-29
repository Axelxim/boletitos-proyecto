<?php

namespace App\Services;

use App\Models\TipoBoletoModel;

class TipoBoletoService
{
    public function __construct(
        private readonly TipoBoletoModel $tipoBoletoModel = new TipoBoletoModel(),
        private readonly EventoService $eventoService = new EventoService(),
        private readonly BitacoraService $bitacoraService = new BitacoraService()
    ) {
    }

    public function obtenerPublicosPorEvento(int $eventoId): array
    {
        $tipos = $this->baseConsultaConEvento()
            ->where('tipos_boletos.evento_id', $eventoId)
            ->where('tipos_boletos.estado', 'activo')
            ->where('tipos_boletos.cantidad_disponible >', 0)
            ->orderBy('tipos_boletos.id', 'ASC')
            ->findAll();

        return array_map([$this, 'transformarTipoBoleto'], $tipos);
    }

    public function obtenerTodosAdmin(): array
    {
        $tipos = $this->baseConsultaConEvento()
            ->orderBy('tipos_boletos.id', 'ASC')
            ->findAll();

        return array_map([$this, 'transformarTipoBoleto'], $tipos);
    }

    public function obtenerPorIdAdmin(int $id): ?array
    {
        $tipo = $this->baseConsultaConEvento()
            ->where('tipos_boletos.id', $id)
            ->first();

        return $tipo ? $this->transformarTipoBoleto($tipo) : null;
    }

    public function crear(array $data): ?array
    {
        $payload = $this->prepararPayload($data, false);
        $inserted = $this->tipoBoletoModel->insert($payload);

        if ($inserted === false) {
            return null;
        }

        $tipo = $this->obtenerPorIdAdmin((int) $inserted);

        $this->bitacoraService->registrar([
            'usuario_id'   => get_authenticated_user()['id'] ?? null,
            'modulo'       => 'tipos_boletos',
            'accion'       => 'create',
            'descripcion'  => 'Tipo de boleto creado correctamente',
            'datos_nuevos' => $tipo,
        ]);

        return $tipo;
    }

    public function actualizar(int $id, array $data): ?array
    {
        $tipoActual = $this->obtenerPorIdAdmin($id);

        if ($tipoActual === null) {
            return null;
        }

        $payload = $this->prepararPayload($data, true);
        $updated = $this->tipoBoletoModel->update($id, $payload);

        if ($updated === false) {
            return null;
        }

        $tipoNuevo = $this->obtenerPorIdAdmin($id);

        $this->bitacoraService->registrar([
            'usuario_id'       => get_authenticated_user()['id'] ?? null,
            'modulo'           => 'tipos_boletos',
            'accion'           => 'update',
            'descripcion'      => 'Tipo de boleto actualizado correctamente',
            'datos_anteriores' => $tipoActual,
            'datos_nuevos'     => $tipoNuevo,
        ]);

        return $tipoNuevo;
    }

    public function eliminar(int $id): bool
    {
        $tipoActual = $this->obtenerPorIdAdmin($id);

        if ($tipoActual === null) {
            return false;
        }

        $deleted = $this->tipoBoletoModel->delete($id);

        if ($deleted === false) {
            return false;
        }

        $this->bitacoraService->registrar([
            'usuario_id'       => get_authenticated_user()['id'] ?? null,
            'modulo'           => 'tipos_boletos',
            'accion'           => 'delete',
            'descripcion'      => 'Tipo de boleto eliminado correctamente',
            'datos_anteriores' => $tipoActual,
        ]);

        return true;
    }

    public function eventoExiste(int $eventoId): bool
    {
        return $this->eventoService->obtenerPorIdAdmin($eventoId) !== null;
    }

    public function actualizarDisponibilidad(int $id, int $cantidadDisponible): bool
    {
        $tipoActual = $this->obtenerPorIdAdmin($id);

        if ($tipoActual === null) {
            return false;
        }

        return $this->tipoBoletoModel->update($id, [
            'cantidad_disponible' => $cantidadDisponible,
            'updated_at'          => app_timestamp(),
        ]);
    }

    public function transformarTipoBoleto(array $tipo): array
    {
        return [
            'id'                  => $tipo['id'],
            'evento_id'           => $tipo['evento_id'],
            'nombre'              => $tipo['nombre'],
            'descripcion'         => $tipo['descripcion'],
            'precio'              => $tipo['precio'],
            'cantidad_total'      => $tipo['cantidad_total'],
            'cantidad_disponible' => $tipo['cantidad_disponible'],
            'max_por_compra'      => $tipo['max_por_compra'],
            'estado'              => $tipo['estado'],
            'created_at'          => $tipo['created_at'],
            'updated_at'          => $tipo['updated_at'],
            'evento'              => $tipo['evento_id'] ? [
                'id'           => $tipo['evento_id'],
                'titulo'       => $tipo['evento_titulo'],
                'estado'       => $tipo['evento_estado'],
                'fecha_inicio' => $tipo['evento_fecha_inicio'],
                'fecha_fin'    => $tipo['evento_fecha_fin'],
            ] : null,
        ];
    }

    private function baseConsultaConEvento(): TipoBoletoModel
    {
        return $this->tipoBoletoModel
            ->select('tipos_boletos.*, eventos.titulo as evento_titulo, eventos.estado as evento_estado, eventos.fecha_inicio as evento_fecha_inicio, eventos.fecha_fin as evento_fecha_fin')
            ->join('eventos', 'eventos.id = tipos_boletos.evento_id', 'left');
    }

    private function prepararPayload(array $data, bool $isUpdate): array
    {
        $payload = [
            'evento_id'           => (int) ($data['evento_id'] ?? 0),
            'nombre'              => sanitize_simple_string($data['nombre'] ?? ''),
            'descripcion'         => sanitize_simple_string($data['descripcion'] ?? ''),
            'precio'              => $data['precio'] ?? 0,
            'cantidad_total'      => (int) ($data['cantidad_total'] ?? 0),
            'cantidad_disponible' => (int) ($data['cantidad_disponible'] ?? 0),
            'max_por_compra'      => (int) ($data['max_por_compra'] ?? 0),
            'estado'              => sanitize_simple_string($data['estado'] ?? 'activo'),
            'updated_at'          => app_timestamp(),
        ];

        if (! $isUpdate) {
            $payload['created_at'] = app_timestamp();
        }

        return $payload;
    }
}
