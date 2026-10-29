<?php

namespace App\Services;

use App\Models\OrdenDetalleModel;

class OrdenDetalleService
{
    public function __construct(
        private readonly OrdenDetalleModel $ordenDetalleModel = new OrdenDetalleModel(),
        private readonly OrdenService $ordenService = new OrdenService(),
        private readonly TipoBoletoService $tipoBoletoService = new TipoBoletoService(),
        private readonly BitacoraService $bitacoraService = new BitacoraService()
    ) {
    }

    public function agregarDetalle(int $ordenId, array $data): array
    {
        $errors = $this->validarDetalle($ordenId, $data);

        if ($errors !== []) {
            return [
                'success' => false,
                'errors'  => $errors,
            ];
        }

        $orden = $this->ordenService->obtenerPorId($ordenId);
        $tipoBoleto = $this->tipoBoletoService->obtenerPorIdAdmin((int) $data['tipo_boleto_id']);
        $cantidad = (int) $data['cantidad'];
        $precioUnitario = (float) $tipoBoleto['precio'];
        $subtotal = round($cantidad * $precioUnitario, 2);

        $inserted = $this->ordenDetalleModel->insert([
            'orden_id'         => $ordenId,
            'tipo_boleto_id'   => (int) $data['tipo_boleto_id'],
            'cantidad'         => $cantidad,
            'precio_unitario'  => $precioUnitario,
            'subtotal'         => $subtotal,
        ]);

        if ($inserted === false) {
            return [
                'success' => false,
                'errors'  => ['detalle' => 'No fue posible agregar el detalle de la orden.'],
            ];
        }

        $detalle = $this->obtenerPorId((int) $inserted);
        $ordenActualizada = $this->ordenService->recalcularTotalesDesdeDetalles($ordenId);

        $this->bitacoraService->registrar([
            'usuario_id'       => $orden['usuario_id'] ?? get_authenticated_user()['id'] ?? null,
            'modulo'           => 'orden_detalles',
            'accion'           => 'create',
            'descripcion'      => 'Detalle de orden agregado correctamente',
            'datos_nuevos'     => [
                'detalle' => $detalle,
                'orden'   => $ordenActualizada,
            ],
        ]);

        return [
            'success' => true,
            'data'    => [
                'detalle' => $detalle,
                'orden'   => $ordenActualizada,
            ],
        ];
    }

    public function obtenerPorId(int $id): ?array
    {
        $detalle = $this->ordenDetalleModel->find($id);

        return $detalle ?: null;
    }

    public function obtenerPorOrdenId(int $ordenId): array
    {
        return $this->ordenDetalleModel
            ->where('orden_id', $ordenId)
            ->findAll();
    }

    private function validarDetalle(int $ordenId, array $data): array
    {
        $errors = [];
        $orden = $this->ordenService->obtenerPorId($ordenId);

        if ($orden === null) {
            $errors['orden_id'] = 'La orden seleccionada no existe.';

            return $errors;
        }

        if (($orden['estado'] ?? '') !== 'pendiente') {
            $errors['orden'] = 'La orden debe estar en estado pendiente.';
        }

        $tipoBoletoId = (int) ($data['tipo_boleto_id'] ?? 0);
        $cantidad = (int) ($data['cantidad'] ?? 0);
        $tipoBoleto = $tipoBoletoId > 0 ? $this->tipoBoletoService->obtenerPorIdAdmin($tipoBoletoId) : null;

        if ($tipoBoleto === null) {
            $errors['tipo_boleto_id'] = 'El tipo de boleto seleccionado no existe.';

            return $errors;
        }

        if (($tipoBoleto['estado'] ?? '') !== 'activo') {
            $errors['tipo_boleto_id'] = 'El tipo de boleto debe estar activo.';
        }

        if ($cantidad <= 0) {
            $errors['cantidad'] = 'La cantidad debe ser mayor a 0.';
        }

        $maxPorCompra = (int) ($tipoBoleto['max_por_compra'] ?? 0);

        if ($maxPorCompra > 0 && $cantidad > $maxPorCompra) {
            $errors['cantidad'] = 'La cantidad no puede superar el maximo por compra.';
        }

        $cantidadDisponible = (int) ($tipoBoleto['cantidad_disponible'] ?? 0);

        if ($cantidad > $cantidadDisponible) {
            $errors['cantidad'] = 'La cantidad no puede superar la cantidad disponible.';
        }

        return $errors;
    }
}
