<?php

namespace App\Services;

use App\Models\EventoModel;
use App\Models\EventoMetricaModel;

class EventoService
{
    public function __construct(
        private readonly EventoModel $eventoModel = new EventoModel(),
        private readonly BitacoraService $bitacoraService = new BitacoraService(),
        private readonly EventoMetricaModel $eventoMetricaModel = new EventoMetricaModel()
    ) {
    }

    public function obtenerPublicados(): array
    {
        $eventos = $this->baseConsultaConRelaciones()
            ->where('eventos.estado', 'publicado')
            ->orderBy('eventos.id', 'ASC')
            ->findAll();

        return array_map([$this, 'transformarEvento'], $eventos);
    }

    public function obtenerPublicadoPorId(int $id): ?array
    {
        $evento = $this->baseConsultaConRelaciones()
            ->where('eventos.id', $id)
            ->where('eventos.estado', 'publicado')
            ->first();

        return $evento ? $this->transformarEvento($evento) : null;
    }

    public function obtenerTodosAdmin(): array
    {
        $eventos = $this->baseConsultaConRelaciones()
            ->orderBy('eventos.id', 'ASC')
            ->findAll();

        return array_map([$this, 'transformarEvento'], $eventos);
    }

    public function obtenerPorIdAdmin(int $id): ?array
    {
        $evento = $this->baseConsultaConRelaciones()
            ->where('eventos.id', $id)
            ->first();

        return $evento ? $this->transformarEvento($evento) : null;
    }

    public function obtenerDestacadosPorScore(int $limit = 5): array
    {
        $eventos = $this->baseConsultaConRelaciones()
            ->select('eventos.*, categorias.nombre as categoria_nombre, categorias.descripcion as categoria_descripcion, categorias.estado as categoria_estado, sedes.nombre as sede_nombre, sedes.direccion as sede_direccion, sedes.ciudad as sede_ciudad, sedes.estado as sede_estado, sedes.pais as sede_pais, sedes.capacidad as sede_capacidad, sedes.estado_sede as sede_estado_sede, COALESCE(evento_metricas.vistas, 0) as metricas_vistas, COALESCE(evento_metricas.clicks, 0) as metricas_clicks, COALESCE(evento_metricas.busquedas, 0) as metricas_busquedas, COALESCE(evento_metricas.ventas, 0) as metricas_ventas, COALESCE(evento_metricas.boletos_vendidos, 0) as metricas_boletos_vendidos, COALESCE(evento_metricas.score, 0) as metricas_score')
            ->join('evento_metricas', 'evento_metricas.evento_id = eventos.id', 'left')
            ->where('eventos.estado', 'publicado')
            ->orderBy('metricas_score', 'DESC')
            ->orderBy('eventos.id', 'ASC')
            ->findAll($limit);

        return array_map([$this, 'transformarEvento'], $eventos);
    }

    public function crear(array $data): ?array
    {
        $payload = $this->prepararPayload($data, false);
        $inserted = $this->eventoModel->insert($payload);

        if ($inserted === false) {
            return null;
        }

        $evento = $this->obtenerPorIdAdmin((int) $inserted);

        $this->bitacoraService->registrar([
            'usuario_id'   => get_authenticated_user()['id'] ?? null,
            'modulo'       => 'eventos',
            'accion'       => 'create',
            'descripcion'  => 'Evento creado correctamente',
            'datos_nuevos' => $evento,
        ]);

        return $evento;
    }

    public function actualizar(int $id, array $data): ?array
    {
        $eventoActual = $this->obtenerPorIdAdmin($id);

        if ($eventoActual === null) {
            return null;
        }

        $payload = $this->prepararPayload($data, true);
        $updated = $this->eventoModel->update($id, $payload);

        if ($updated === false) {
            return null;
        }

        $eventoNuevo = $this->obtenerPorIdAdmin($id);

        $this->bitacoraService->registrar([
            'usuario_id'       => get_authenticated_user()['id'] ?? null,
            'modulo'           => 'eventos',
            'accion'           => 'update',
            'descripcion'      => 'Evento actualizado correctamente',
            'datos_anteriores' => $eventoActual,
            'datos_nuevos'     => $eventoNuevo,
        ]);

        return $eventoNuevo;
    }

    public function eliminar(int $id): bool
    {
        $eventoActual = $this->obtenerPorIdAdmin($id);

        if ($eventoActual === null) {
            return false;
        }

        $deleted = $this->eventoModel->delete($id);

        if ($deleted === false) {
            return false;
        }

        $this->bitacoraService->registrar([
            'usuario_id'       => get_authenticated_user()['id'] ?? null,
            'modulo'           => 'eventos',
            'accion'           => 'delete',
            'descripcion'      => 'Evento eliminado correctamente',
            'datos_anteriores' => $eventoActual,
        ]);

        return true;
    }

    public function transformarEvento(array $evento): array
    {
        $metricas = $this->resolverMetricas($evento);

        return [
            'id'           => $evento['id'],
            'categoria_id' => $evento['categoria_id'],
            'sede_id'      => $evento['sede_id'],
            'titulo'       => $evento['titulo'],
            'descripcion'  => $evento['descripcion'],
            'lugar'        => $evento['lugar'],
            'direccion'    => $evento['direccion'],
            'fecha_inicio' => $evento['fecha_inicio'],
            'fecha_fin'    => $evento['fecha_fin'],
            'fecha_evento' => $evento['fecha_evento'],
            'hora_evento'  => $evento['hora_evento'],
            'imagen'       => $evento['imagen'],
            'estado'       => $evento['estado'],
            'created_at'   => $evento['created_at'],
            'updated_at'   => $evento['updated_at'],
            'metricas'     => $metricas,
            'categoria'    => $evento['categoria_id'] ? [
                'id'          => $evento['categoria_id'],
                'nombre'      => $evento['categoria_nombre'],
                'descripcion' => $evento['categoria_descripcion'],
                'estado'      => $evento['categoria_estado'],
            ] : null,
            'sede'         => $evento['sede_id'] ? [
                'id'          => $evento['sede_id'],
                'nombre'      => $evento['sede_nombre'],
                'direccion'   => $evento['sede_direccion'],
                'ciudad'      => $evento['sede_ciudad'],
                'estado'      => $evento['sede_estado'],
                'pais'        => $evento['sede_pais'],
                'capacidad'   => $evento['sede_capacidad'],
                'estado_sede' => $evento['sede_estado_sede'],
            ] : null,
        ];
    }

    private function baseConsultaConRelaciones(): EventoModel
    {
        return $this->eventoModel
            ->select('eventos.*, categorias.nombre as categoria_nombre, categorias.descripcion as categoria_descripcion, categorias.estado as categoria_estado, sedes.nombre as sede_nombre, sedes.direccion as sede_direccion, sedes.ciudad as sede_ciudad, sedes.estado as sede_estado, sedes.pais as sede_pais, sedes.capacidad as sede_capacidad, sedes.estado_sede as sede_estado_sede')
            ->join('categorias', 'categorias.id = eventos.categoria_id', 'left')
            ->join('sedes', 'sedes.id = eventos.sede_id', 'left');
    }

    private function prepararPayload(array $data, bool $isUpdate): array
    {
        $payload = [
            'categoria_id' => (int) ($data['categoria_id'] ?? 0),
            'sede_id'      => (int) ($data['sede_id'] ?? 0),
            'titulo'       => sanitize_simple_string($data['titulo'] ?? ''),
            'descripcion'  => sanitize_simple_string($data['descripcion'] ?? ''),
            'lugar'        => sanitize_simple_string($data['lugar'] ?? ''),
            'direccion'    => sanitize_simple_string($data['direccion'] ?? ''),
            'fecha_inicio' => sanitize_simple_string($data['fecha_inicio'] ?? ''),
            'fecha_fin'    => sanitize_simple_string($data['fecha_fin'] ?? ''),
            'fecha_evento' => sanitize_simple_string($data['fecha_evento'] ?? ''),
            'hora_evento'  => sanitize_simple_string($data['hora_evento'] ?? ''),
            'imagen'       => sanitize_simple_string($data['imagen'] ?? ''),
            'estado'       => sanitize_simple_string($data['estado'] ?? 'borrador'),
            'updated_at'   => app_timestamp(),
        ];

        if (! $isUpdate) {
            $payload['created_at'] = app_timestamp();
        }

        return $payload;
    }

    private function resolverMetricas(array $evento): array
    {
        $hasMetricAliases = array_key_exists('metricas_score', $evento)
            || array_key_exists('metricas_vistas', $evento)
            || array_key_exists('metricas_clicks', $evento)
            || array_key_exists('metricas_busquedas', $evento)
            || array_key_exists('metricas_ventas', $evento)
            || array_key_exists('metricas_boletos_vendidos', $evento);

        if ($hasMetricAliases) {
            return [
                'vistas'           => (int) ($evento['metricas_vistas'] ?? 0),
                'clicks'           => (int) ($evento['metricas_clicks'] ?? 0),
                'busquedas'        => (int) ($evento['metricas_busquedas'] ?? 0),
                'ventas'           => (int) ($evento['metricas_ventas'] ?? 0),
                'boletos_vendidos' => (int) ($evento['metricas_boletos_vendidos'] ?? 0),
                'score'            => (float) ($evento['metricas_score'] ?? 0),
            ];
        }

        $metrica = $this->eventoMetricaModel->where('evento_id', (int) ($evento['id'] ?? 0))->first();

        return [
            'vistas'           => (int) ($metrica['vistas'] ?? 0),
            'clicks'           => (int) ($metrica['clicks'] ?? 0),
            'busquedas'        => (int) ($metrica['busquedas'] ?? 0),
            'ventas'           => (int) ($metrica['ventas'] ?? 0),
            'boletos_vendidos' => (int) ($metrica['boletos_vendidos'] ?? 0),
            'score'            => (float) ($metrica['score'] ?? 0),
        ];
    }
}
