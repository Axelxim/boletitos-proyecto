<?php

namespace App\Services;

use App\Models\BoletoModel;
use App\Models\EventoImagenModel;
use App\Models\OrdenDetalleModel;
use App\Models\OrdenModel;
use Config\App;

class OrdenService
{
    public function __construct(
        private readonly OrdenModel $ordenModel = new OrdenModel(),
        private readonly BitacoraService $bitacoraService = new BitacoraService()
    ) {
    }

    public function obtenerTodas(): array
    {
        return $this->ordenModel
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    public function obtenerTodasAdminEnriquecidas(): array
    {
        return array_map(
            fn (array $orden): array => $this->enriquecerOrden($orden),
            $this->obtenerTodas()
        );
    }

    public function obtenerPorId(int $id): ?array
    {
        $orden = $this->ordenModel->find($id);

        return $orden ?: null;
    }

    public function obtenerPorIdAdminEnriquecida(int $id): ?array
    {
        $orden = $this->obtenerPorId($id);

        if ($orden === null) {
            return null;
        }

        return $this->enriquecerOrden($orden);
    }

    public function obtenerPorUsuarioId(int $usuarioId): array
    {
        $ordenes = $this->ordenModel
            ->where('usuario_id', $usuarioId)
            ->orderBy('id', 'DESC')
            ->findAll();

        return array_map(
            fn (array $orden): array => $this->enriquecerOrden($orden),
            $ordenes
        );
    }

    public function obtenerPorFolioYCorreo(string $folio, string $correo): ?array
    {
        $orden = $this->ordenModel
            ->where('folio', sanitize_simple_string($folio))
            ->where('correo_comprador', sanitize_simple_string($correo))
            ->first();

        if ($orden === null) {
            return null;
        }

        return $this->enriquecerOrden($orden);
    }

    public function actualizarEstado(int $ordenId, string $estado): ?array
    {
        $orden = $this->obtenerPorId($ordenId);

        if ($orden === null) {
            return null;
        }

        $this->ordenModel->update($ordenId, [
            'estado'     => sanitize_simple_string($estado),
            'updated_at' => app_timestamp(),
        ]);

        return $this->obtenerPorId($ordenId);
    }

    public function tieneDetalles(int $ordenId): bool
    {
        $detalleModel = new OrdenDetalleModel();

        return $detalleModel->where('orden_id', $ordenId)->countAllResults() > 0;
    }

    public function recalcularTotalesDesdeDetalles(int $ordenId): ?array
    {
        $orden = $this->obtenerPorId($ordenId);

        if ($orden === null) {
            return null;
        }

        $detalleModel = new OrdenDetalleModel();
        $detalles = $detalleModel->where('orden_id', $ordenId)->findAll();
        $subtotal = round(array_reduce($detalles, static fn (float $carry, array $detalle): float => $carry + (float) ($detalle['subtotal'] ?? 0), 0.0), 2);
        $comision = round((float) ($orden['comision'] ?? 0), 2);
        $total = round($subtotal + $comision, 2);

        $this->ordenModel->update($ordenId, [
            'subtotal'   => $subtotal,
            'comision'   => $comision,
            'total'      => $total,
            'updated_at' => app_timestamp(),
        ]);

        return $this->obtenerPorId($ordenId);
    }

    public function crear(array $data): ?array
    {
        $payload = $this->prepararPayload($data);
        $inserted = $this->ordenModel->insert($payload);

        if ($inserted === false) {
            return null;
        }

        $orden = $this->obtenerPorId((int) $inserted);

        $this->bitacoraService->registrar([
            'usuario_id'   => $orden['usuario_id'] ?? null,
            'modulo'       => 'ordenes',
            'accion'       => 'create',
            'descripcion'  => 'Orden creada correctamente',
            'datos_nuevos' => $orden,
        ]);

        return $orden;
    }

    private function prepararPayload(array $data): array
    {
        $authenticatedUser = get_authenticated_user();
        $usuarioIdAutenticado = isset($authenticatedUser['id']) ? (int) $authenticatedUser['id'] : null;
        $correoAutenticado = sanitize_simple_string($authenticatedUser['correo'] ?? '');

        return [
            'usuario_id'          => $usuarioIdAutenticado ?: null,
            'folio'               => $this->generarFolio(),
            'nombre_comprador'    => sanitize_simple_string($data['nombre_comprador'] ?? ''),
            'correo_comprador'    => $correoAutenticado !== '' ? $correoAutenticado : sanitize_simple_string($data['correo_comprador'] ?? ''),
            'telefono_comprador'  => sanitize_simple_string($data['telefono_comprador'] ?? ''),
            'subtotal'            => $data['subtotal'] ?? 0,
            'comision'            => $data['comision'] ?? 0,
            'total'               => $data['total'] ?? 0,
            'estado'              => 'pendiente',
            'created_at'          => app_timestamp(),
            'updated_at'          => app_timestamp(),
        ];
    }

    private function generarFolio(): string
    {
        $sequence = $this->ordenModel->countAllResults() + 1;

        return generate_folio('ORD', $sequence);
    }

    private function enriquecerOrden(array $orden): array
    {
        $detalles = $this->obtenerDetallesEnriquecidos((int) $orden['id']);
        $boletos = $this->obtenerBoletosEnriquecidos((int) $orden['id']);
        $evento = $this->resolverEventoDesdeDetalles($detalles);

        return array_merge($orden, [
            'detalles'          => $detalles,
            'evento'            => $evento,
            'boletos_generados' => $boletos,
            'cantidad_boletos'  => array_reduce(
                $detalles,
                static fn (int $carry, array $detalle): int => $carry + (int) ($detalle['cantidad'] ?? 0),
                0
            ),
        ]);
    }

    private function obtenerDetallesEnriquecidos(int $ordenId): array
    {
        $detalleModel = new OrdenDetalleModel();
        $rows = $detalleModel
            ->select('orden_detalles.*, tipos_boletos.nombre as tipo_boleto_nombre, tipos_boletos.descripcion as tipo_boleto_descripcion, tipos_boletos.estado as tipo_boleto_estado, tipos_boletos.evento_id as evento_id, eventos.titulo as evento_titulo, eventos.fecha_inicio as evento_fecha_inicio, eventos.fecha_fin as evento_fecha_fin, eventos.lugar as evento_lugar, eventos.sede_id as evento_sede_id, eventos.categoria_id as evento_categoria_id, sedes.nombre as sede_nombre, sedes.direccion as sede_direccion')
            ->join('tipos_boletos', 'tipos_boletos.id = orden_detalles.tipo_boleto_id', 'left')
            ->join('eventos', 'eventos.id = tipos_boletos.evento_id', 'left')
            ->join('sedes', 'sedes.id = eventos.sede_id', 'left')
            ->where('orden_detalles.orden_id', $ordenId)
            ->orderBy('orden_detalles.id', 'ASC')
            ->findAll();

        return array_map(function (array $detalle): array {
            return [
                'id'              => $detalle['id'],
                'orden_id'        => $detalle['orden_id'],
                'tipo_boleto_id'  => $detalle['tipo_boleto_id'],
                'cantidad'        => (int) $detalle['cantidad'],
                'precio_unitario' => (float) $detalle['precio_unitario'],
                'subtotal'        => (float) $detalle['subtotal'],
                'tipo_boleto'     => $detalle['tipo_boleto_id'] ? [
                    'id'          => $detalle['tipo_boleto_id'],
                    'nombre'      => $detalle['tipo_boleto_nombre'],
                    'descripcion' => $detalle['tipo_boleto_descripcion'],
                    'estado'      => $detalle['tipo_boleto_estado'],
                    'evento_id'   => $detalle['evento_id'],
                ] : null,
                'evento'          => $detalle['evento_id'] ? [
                    'id'           => $detalle['evento_id'],
                    'categoria_id' => $detalle['evento_categoria_id'],
                    'titulo'       => $detalle['evento_titulo'],
                    'fecha_inicio' => $detalle['evento_fecha_inicio'],
                    'fecha_fin'    => $detalle['evento_fecha_fin'],
                    'lugar'        => $detalle['evento_lugar'],
                    'sede'         => $detalle['evento_sede_id'] ? [
                        'id'        => $detalle['evento_sede_id'],
                        'nombre'    => $detalle['sede_nombre'],
                        'direccion' => $detalle['sede_direccion'],
                    ] : null,
                ] : null,
            ];
        }, $rows);
    }

    private function obtenerBoletosEnriquecidos(int $ordenId): array
    {
        $boletoModel = new BoletoModel();
        $rows = $boletoModel
            ->select('boletos.*, tipos_boletos.nombre as tipo_boleto_nombre, eventos.id as evento_id, eventos.titulo as evento_titulo')
            ->join('tipos_boletos', 'tipos_boletos.id = boletos.tipo_boleto_id', 'left')
            ->join('eventos', 'eventos.id = tipos_boletos.evento_id', 'left')
            ->where('boletos.orden_id', $ordenId)
            ->orderBy('boletos.id', 'ASC')
            ->findAll();

        return array_map(function (array $boleto): array {
            return [
                'id'             => $boleto['id'],
                'orden_id'       => $boleto['orden_id'],
                'tipo_boleto_id' => $boleto['tipo_boleto_id'],
                'folio'          => $boleto['folio'],
                'codigo_qr'      => $boleto['codigo_qr'],
                'estado'         => $boleto['estado'],
                'fecha_uso'      => $boleto['fecha_uso'],
                'created_at'     => $boleto['created_at'],
                'updated_at'     => $boleto['updated_at'],
                'tipo_boleto'    => $boleto['tipo_boleto_id'] ? [
                    'id'     => $boleto['tipo_boleto_id'],
                    'nombre' => $boleto['tipo_boleto_nombre'],
                ] : null,
                'evento'         => $boleto['evento_id'] ? [
                    'id'     => $boleto['evento_id'],
                    'titulo' => $boleto['evento_titulo'],
                ] : null,
            ];
        }, $rows);
    }

    private function resolverEventoDesdeDetalles(array $detalles): ?array
    {
        $primerDetalle = $detalles[0] ?? null;

        if ($primerDetalle === null || ! isset($primerDetalle['evento']['id'])) {
            return null;
        }

        $evento = $primerDetalle['evento'];
        $imagen = $this->obtenerImagenEvento((int) $evento['id']);

        return array_merge($evento, [
            'imagen' => $imagen,
        ]);
    }

    private function obtenerImagenEvento(int $eventoId): ?string
    {
        $imagenModel = new EventoImagenModel();
        $imagen = $imagenModel
            ->where('evento_id', $eventoId)
            ->where('estado', 'activo')
            ->groupStart()
                ->where('tipo', 'portada')
                ->orWhere('tipo', 'banner')
            ->groupEnd()
            ->orderBy('principal', 'DESC')
            ->orderBy('orden', 'ASC')
            ->orderBy('id', 'ASC')
            ->first();

        if (! $imagen || empty($imagen['ruta'])) {
            return null;
        }

        $baseUrl = rtrim(config(App::class)->baseURL, '/');

        return $baseUrl . '/' . ltrim((string) $imagen['ruta'], '/');
    }
}
