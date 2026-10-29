<?php

namespace App\Services;

use App\Models\BoletoModel;

class BoletoService
{
    public function __construct(
        private readonly BoletoModel $boletoModel = new BoletoModel(),
        private readonly OrdenService $ordenService = new OrdenService(),
        private readonly OrdenDetalleService $ordenDetalleService = new OrdenDetalleService(),
        private readonly TipoBoletoService $tipoBoletoService = new TipoBoletoService(),
        private readonly BitacoraService $bitacoraService = new BitacoraService()
    ) {
    }

    public function generarPorOrden(int $ordenId): array
    {
        $errors = $this->validarGeneracion($ordenId);

        if ($errors !== []) {
            return [
                'success' => false,
                'errors'  => $errors,
            ];
        }

        $orden = $this->ordenService->obtenerPorId($ordenId);
        $detalles = $this->ordenDetalleService->obtenerPorOrdenId($ordenId);
        $boletosGenerados = [];
        $sequence = $this->boletoModel->countAllResults() + 1;

        foreach ($detalles as $detalle) {
            $tipoBoleto = $this->tipoBoletoService->obtenerPorIdAdmin((int) $detalle['tipo_boleto_id']);
            $cantidad = (int) $detalle['cantidad'];
            $cantidadDisponible = (int) ($tipoBoleto['cantidad_disponible'] ?? 0);

            if ($cantidad > $cantidadDisponible) {
                return [
                    'success' => false,
                    'errors'  => ['cantidad' => 'No hay disponibilidad suficiente para generar los boletos.'],
                ];
            }

            for ($i = 0; $i < $cantidad; $i++) {
                $inserted = $this->boletoModel->insert([
                    'orden_id'         => $ordenId,
                    'tipo_boleto_id'   => (int) $detalle['tipo_boleto_id'],
                    'folio'            => generate_folio('BOL', $sequence),
                    'codigo_qr'        => $this->generarCodigoQrProvisional($ordenId, (int) $detalle['tipo_boleto_id'], $sequence),
                    'estado'           => 'vigente',
                    'fecha_uso'        => null,
                    'created_at'       => app_timestamp(),
                    'updated_at'       => app_timestamp(),
                ]);

                if ($inserted === false) {
                    return [
                        'success' => false,
                        'errors'  => ['boleto' => 'No fue posible generar los boletos de la orden.'],
                    ];
                }

                $boletosGenerados[] = $this->obtenerPorId((int) $inserted);
                $sequence++;
            }

            $this->tipoBoletoService->actualizarDisponibilidad(
                (int) $detalle['tipo_boleto_id'],
                $cantidadDisponible - $cantidad
            );
        }

        $this->bitacoraService->registrar([
            'usuario_id'   => $orden['usuario_id'] ?? get_authenticated_user()['id'] ?? null,
            'modulo'       => 'boletos',
            'accion'       => 'create',
            'descripcion'  => 'Boletos generados correctamente para la orden',
            'datos_nuevos' => [
                'orden_id' => $ordenId,
                'boletos'  => $boletosGenerados,
            ],
        ]);

        return [
            'success' => true,
            'data'    => [
                'orden'   => $orden,
                'boletos' => $boletosGenerados,
            ],
        ];
    }

    public function obtenerPorId(int $id): ?array
    {
        $boleto = $this->baseConsultaConRelaciones()
            ->where('boletos.id', $id)
            ->first();

        return $boleto ? $this->transformarBoleto($boleto) : null;
    }

    public function obtenerTodosAdmin(): array
    {
        $boletos = $this->baseConsultaConRelaciones()
            ->orderBy('boletos.id', 'DESC')
            ->findAll();

        return array_map([$this, 'transformarBoleto'], $boletos);
    }

    public function obtenerPorOrden(int $ordenId): array
    {
        $boletos = $this->baseConsultaConRelaciones()
            ->where('boletos.orden_id', $ordenId)
            ->orderBy('boletos.id', 'ASC')
            ->findAll();

        return array_map([$this, 'transformarBoleto'], $boletos);
    }

    public function obtenerPorCodigoQr(string $codigoQr): ?array
    {
        $boleto = $this->baseConsultaConRelaciones()
            ->where('boletos.codigo_qr', $codigoQr)
            ->first();

        return $boleto ? $this->transformarBoleto($boleto) : null;
    }

    public function obtenerPorCodigoQrOFolio(string $codigo): ?array
    {
        $codigo = sanitize_simple_string($codigo);

        $boleto = $this->baseConsultaConRelaciones()
            ->groupStart()
                ->where('boletos.codigo_qr', $codigo)
                ->orWhere('boletos.folio', $codigo)
            ->groupEnd()
            ->first();

        return $boleto ? $this->transformarBoleto($boleto) : null;
    }

    public function marcarComoUsado(int $id): ?array
    {
        $boleto = $this->obtenerPorId($id);

        if ($boleto === null) {
            return null;
        }

        $this->boletoModel->update($id, [
            'estado'     => 'usado',
            'fecha_uso'  => app_timestamp(),
            'updated_at' => app_timestamp(),
        ]);

        return $this->obtenerPorId($id);
    }

    public function ordenTieneBoletos(int $ordenId): bool
    {
        return $this->boletoModel->where('orden_id', $ordenId)->countAllResults() > 0;
    }

    private function validarGeneracion(int $ordenId): array
    {
        $errors = [];
        $orden = $this->ordenService->obtenerPorId($ordenId);

        if ($orden === null) {
            $errors['orden_id'] = 'La orden seleccionada no existe.';

            return $errors;
        }

        if (($orden['estado'] ?? '') !== 'pagada') {
            $errors['orden'] = 'La orden debe estar pagada.';
        }

        $detalles = $this->ordenDetalleService->obtenerPorOrdenId($ordenId);

        if ($detalles === []) {
            $errors['orden'] = 'La orden debe tener detalles.';
        }

        if ($this->ordenTieneBoletos($ordenId)) {
            $errors['orden'] = 'La orden ya tiene boletos generados.';
        }

        return $errors;
    }

    private function generarCodigoQrProvisional(int $ordenId, int $tipoBoletoId, int $sequence): string
    {
        return hash('sha256', implode('|', [$ordenId, $tipoBoletoId, $sequence, app_timestamp()]));
    }

    private function baseConsultaConRelaciones(): BoletoModel
    {
        return $this->boletoModel
            ->select('boletos.*, ordenes.folio as orden_folio, ordenes.estado as orden_estado, ordenes.total as orden_total, tipos_boletos.nombre as tipo_boleto_nombre, tipos_boletos.precio as tipo_boleto_precio, tipos_boletos.estado as tipo_boleto_estado, tipos_boletos.evento_id as evento_id, eventos.titulo as evento_titulo, eventos.fecha_inicio as evento_fecha_inicio, eventos.fecha_fin as evento_fecha_fin, eventos.estado as evento_estado')
            ->join('ordenes', 'ordenes.id = boletos.orden_id', 'left')
            ->join('tipos_boletos', 'tipos_boletos.id = boletos.tipo_boleto_id', 'left')
            ->join('eventos', 'eventos.id = tipos_boletos.evento_id', 'left');
    }

    private function transformarBoleto(array $boleto): array
    {
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
            'orden'          => $boleto['orden_id'] ? [
                'id'     => $boleto['orden_id'],
                'folio'  => $boleto['orden_folio'],
                'estado' => $boleto['orden_estado'],
                'total'  => $boleto['orden_total'],
            ] : null,
            'tipo_boleto'    => $boleto['tipo_boleto_id'] ? [
                'id'        => $boleto['tipo_boleto_id'],
                'nombre'    => $boleto['tipo_boleto_nombre'],
                'precio'    => $boleto['tipo_boleto_precio'],
                'estado'    => $boleto['tipo_boleto_estado'],
                'evento_id' => $boleto['evento_id'],
            ] : null,
            'evento'         => $boleto['evento_id'] ? [
                'id'           => $boleto['evento_id'],
                'titulo'       => $boleto['evento_titulo'],
                'fecha_inicio' => $boleto['evento_fecha_inicio'],
                'fecha_fin'    => $boleto['evento_fecha_fin'],
                'estado'       => $boleto['evento_estado'],
            ] : null,
        ];
    }
}
