<?php

namespace App\Services;

use App\Models\PagoModel;
use Config\Database;
use RuntimeException;
use Throwable;

class PagoService
{
    public function __construct(
        private readonly PagoModel $pagoModel = new PagoModel(),
        private readonly OrdenService $ordenService = new OrdenService(),
        private readonly BoletoService $boletoService = new BoletoService(),
        private readonly BoletoCorreoService $boletoCorreoService = new BoletoCorreoService(),
        private readonly BitacoraService $bitacoraService = new BitacoraService(),
        private readonly PayPalService $payPalService = new PayPalService(),
        private readonly ConfiguracionService $configuracionService = new ConfiguracionService()
    ) {
    }

    public function registrarPagoSimulado(int $ordenId, array $data): array
    {
        $errors = $this->validarPagoSimulado($ordenId, $data);

        if ($errors !== []) {
            return [
                'success' => false,
                'errors'  => $errors,
            ];
        }

        $orden = $this->ordenService->obtenerPorId($ordenId);
        $estadoPago = sanitize_simple_string($data['estado'] ?? '');
        $nuevoEstadoOrden = $this->mapearEstadoOrden($estadoPago);

        $inserted = $this->pagoModel->insert([
            'orden_id'         => $ordenId,
            'proveedor'        => 'simulado',
            'referencia_pago'  => sanitize_simple_string($data['referencia_pago'] ?? ''),
            'monto'            => $orden['total'],
            'moneda'           => 'MXN',
            'estado'           => $estadoPago,
            'respuesta_json'   => json_encode([
                'modo'            => 'simulado',
                'estado_solicitud'=> $estadoPago,
                'orden_id'        => $ordenId,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at'       => app_timestamp(),
            'updated_at'       => app_timestamp(),
        ]);

        if ($inserted === false) {
            return [
                'success' => false,
                'errors'  => ['pago' => 'No fue posible registrar el pago simulado.'],
            ];
        }

        $ordenActualizada = $this->ordenService->actualizarEstado($ordenId, $nuevoEstadoOrden);
        $pago = $this->obtenerPorId((int) $inserted);
        $boletos = null;
        $warning = null;
        $correo = null;

        if ($estadoPago === 'aprobado' && ! $this->boletoService->ordenTieneBoletos($ordenId)) {
            $generacion = $this->boletoService->generarPorOrden($ordenId);

            if ($generacion['success'] ?? false) {
                $boletos = $generacion['data']['boletos'] ?? [];
                $correo = $this->boletoCorreoService->enviarCompraConfirmada($ordenId);

                if (! ($correo['success'] ?? false)) {
                    $warning = trim(($warning ? $warning . ' ' : '') . 'La compra fue exitosa, pero el correo no pudo enviarse automaticamente.');
                }
            } else {
                $warning = 'El pago fue aprobado, pero los boletos no se generaron automaticamente.';

                $this->bitacoraService->registrar([
                    'usuario_id'   => $orden['usuario_id'] ?? get_authenticated_user()['id'] ?? null,
                    'modulo'       => 'pagos',
                    'accion'       => 'warning',
                    'descripcion'  => 'Pago aprobado sin generacion automatica de boletos',
                    'datos_nuevos' => [
                        'orden_id' => $ordenId,
                        'errors'   => $generacion['errors'] ?? null,
                    ],
                ]);
            }
        }

        $this->bitacoraService->registrar([
            'usuario_id'   => $orden['usuario_id'] ?? get_authenticated_user()['id'] ?? null,
            'modulo'       => 'pagos',
            'accion'       => 'create',
            'descripcion'  => 'Pago simulado registrado correctamente',
            'datos_nuevos' => [
                'pago'  => $pago,
                'orden' => $ordenActualizada,
            ],
        ]);

        return [
            'success' => true,
            'data'    => [
                'pago'    => $pago,
                'orden'   => $ordenActualizada,
                'boletos' => $boletos,
                'warning' => $warning,
                'correo'  => $correo,
            ],
        ];
    }

    public function obtenerPorId(int $id): ?array
    {
        $pago = $this->pagoModel->find($id);

        return $pago ?: null;
    }

    public function obtenerPaypalConfiguracionPublica(): array
    {
        $moneda = $this->obtenerMoneda();

        return $this->payPalService->obtenerConfiguracionPublica($moneda);
    }

    public function crearOrdenPaypal(int $ordenId): array
    {
        $orden = $this->ordenService->obtenerPorId($ordenId);

        if ($orden === null) {
            return [
                'success' => false,
                'errors'  => ['orden_id' => 'La orden seleccionada no existe.'],
                'status'  => 404,
            ];
        }

        if (($orden['estado'] ?? '') !== 'pendiente') {
            return [
                'success' => false,
                'errors'  => ['orden' => 'La orden debe estar en estado pendiente.'],
                'status'  => 422,
            ];
        }

        if (! $this->ordenService->tieneDetalles($ordenId)) {
            return [
                'success' => false,
                'errors'  => ['orden' => 'La orden debe tener al menos un detalle.'],
                'status'  => 422,
            ];
        }

        $moneda = $this->obtenerMoneda();
        $pagoExistente = $this->obtenerPagoPaypalPendientePorOrden($ordenId);

        if ($pagoExistente !== null && ! empty($pagoExistente['referencia_pago'])) {
            return [
                'success' => true,
                'data'    => [
                    'paypal_order_id' => $pagoExistente['referencia_pago'],
                    'estado'          => $pagoExistente['estado'],
                    'monto'           => (float) $pagoExistente['monto'],
                    'moneda'          => $pagoExistente['moneda'],
                ],
                'status'  => 200,
            ];
        }

        try {
            $paypalOrder = $this->payPalService->crearOrden($orden, $moneda);
        } catch (Throwable $exception) {
            log_message('error', 'Error al crear orden PayPal para orden {orden_id}: {message}', [
                'orden_id' => $ordenId,
                'message'  => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'errors'  => [
                    'paypal' => 'No fue posible crear la orden en PayPal.',
                    'detalle' => $exception->getMessage(),
                ],
                'status'  => 502,
            ];
        }

        $sanitized = $this->payPalService->sanitizarRespuesta($paypalOrder);
        $pagoId = $this->pagoModel->insert([
            'orden_id'        => $ordenId,
            'proveedor'       => 'paypal',
            'referencia_pago' => (string) ($paypalOrder['id'] ?? ''),
            'monto'           => $orden['total'],
            'moneda'          => $moneda,
            'estado'          => 'pendiente',
            'respuesta_json'  => json_encode($sanitized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at'      => app_timestamp(),
            'updated_at'      => app_timestamp(),
        ]);

        if ($pagoId === false) {
            return [
                'success' => false,
                'errors'  => ['pago' => 'No fue posible registrar la orden PayPal localmente.'],
                'status'  => 500,
            ];
        }

        $this->bitacoraService->registrar([
            'usuario_id'   => $orden['usuario_id'] ?? get_authenticated_user()['id'] ?? null,
            'modulo'       => 'pagos',
            'accion'       => 'create_paypal_order',
            'descripcion'  => 'Orden PayPal creada correctamente',
            'datos_nuevos' => [
                'orden_id'        => $ordenId,
                'referencia_pago' => $paypalOrder['id'] ?? null,
                'monto'           => $orden['total'],
                'moneda'          => $moneda,
            ],
        ]);

        return [
            'success' => true,
            'data'    => [
                'paypal_order_id' => $paypalOrder['id'] ?? null,
                'estado'          => 'pendiente',
                'monto'           => (float) $orden['total'],
                'moneda'          => $moneda,
            ],
            'status'  => 201,
        ];
    }

    public function capturarOrdenPaypal(int $ordenId, string $paypalOrderId): array
    {
        $orden = $this->ordenService->obtenerPorId($ordenId);

        if ($orden === null) {
            return [
                'success' => false,
                'errors'  => ['orden_id' => 'La orden seleccionada no existe.'],
                'status'  => 404,
            ];
        }

        $pago = $this->obtenerPagoPaypalPorOrdenYReferencia($ordenId, $paypalOrderId);

        if ($pago === null) {
            return [
                'success' => false,
                'errors'  => ['paypal_order_id' => 'La referencia PayPal no corresponde con la orden local.'],
                'status'  => 404,
            ];
        }

        if (($orden['estado'] ?? '') === 'pagada' && $this->boletoService->ordenTieneBoletos($ordenId)) {
            return [
                'success' => true,
                'data'    => $this->buildCaptureSuccessPayload($ordenId, $paypalOrderId, $pago, 'La orden ya habia sido pagada previamente.'),
                'status'  => 200,
            ];
        }

        if (($orden['estado'] ?? '') !== 'pendiente') {
            return [
                'success' => false,
                'errors'  => ['orden' => 'La orden ya no se encuentra pendiente para captura.'],
                'status'  => 422,
            ];
        }

        $moneda = $this->obtenerMoneda();

        try {
            $paypalOrder = $this->payPalService->obtenerOrden($paypalOrderId);
        } catch (Throwable $exception) {
            log_message('error', 'Error al consultar orden PayPal {paypal_order_id}: {message}', [
                'paypal_order_id' => $paypalOrderId,
                'message'         => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'errors'  => [
                    'paypal' => 'No fue posible consultar la orden PayPal.',
                    'detalle' => $exception->getMessage(),
                ],
                'status'  => 502,
            ];
        }

        $validationErrors = $this->validarOrdenPaypal($paypalOrder, $orden, $pago, $moneda);

        if ($validationErrors !== []) {
            $this->registrarIntentoPaypalFallido($ordenId, $pago, $paypalOrder, 'validacion');

            return [
                'success' => false,
                'errors'  => $validationErrors,
                'status'  => 422,
            ];
        }

        try {
            $capture = $this->payPalService->capturarOrden($paypalOrderId);
        } catch (Throwable $exception) {
            log_message('error', 'Error al capturar orden PayPal {paypal_order_id}: {message}', [
                'paypal_order_id' => $paypalOrderId,
                'message'         => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'errors'  => [
                    'paypal' => 'No fue posible capturar la orden PayPal.',
                    'detalle' => $exception->getMessage(),
                ],
                'status'  => 502,
            ];
        }

        $captureValidationErrors = $this->validarCapturaPaypal($capture, $orden, $pago, $moneda);

        if ($captureValidationErrors !== []) {
            $this->registrarIntentoPaypalFallido($ordenId, $pago, $capture, 'captura_rechazada');

            return [
                'success' => false,
                'errors'  => $captureValidationErrors,
                'status'  => 422,
            ];
        }

        $db = Database::connect();
        $correo = null;
        $warning = null;

        try {
            $db->transBegin();

            $this->pagoModel->update((int) $pago['id'], [
                'estado'         => 'aprobado',
                'respuesta_json' => json_encode($this->payPalService->sanitizarRespuesta($capture), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at'     => app_timestamp(),
            ]);

            $ordenActualizada = $this->ordenService->actualizarEstado($ordenId, 'pagada');

            if (! $this->boletoService->ordenTieneBoletos($ordenId)) {
                $generacion = $this->boletoService->generarPorOrden($ordenId);

                if (! ($generacion['success'] ?? false)) {
                    throw new RuntimeException('No fue posible generar los boletos despues de capturar el pago.');
                }
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('La transaccion local del pago PayPal no pudo completarse.');
            }

            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();

            log_message('error', 'Error en transaccion local al capturar PayPal para orden {orden_id}: {message}', [
                'orden_id' => $ordenId,
                'message'  => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'errors'  => ['pago' => 'El pago fue capturado en PayPal, pero no se pudo completar el procesamiento local.'],
                'status'  => 500,
            ];
        }

        $correo = $this->boletoCorreoService->enviarCompraConfirmada($ordenId);

        if (! ($correo['success'] ?? false)) {
            $warning = 'El pago fue aprobado y los boletos se generaron, pero el correo no pudo enviarse automaticamente.';
        }

        $this->bitacoraService->registrar([
            'usuario_id'   => $orden['usuario_id'] ?? get_authenticated_user()['id'] ?? null,
            'modulo'       => 'pagos',
            'accion'       => 'capture_paypal',
            'descripcion'  => 'Pago PayPal capturado correctamente',
            'datos_nuevos' => [
                'orden_id'        => $ordenId,
                'referencia_pago' => $paypalOrderId,
                'estado'          => 'aprobado',
            ],
        ]);

        $pagoActualizado = $this->obtenerPagoPaypalPorOrdenYReferencia($ordenId, $paypalOrderId) ?? $pago;

        return [
            'success' => true,
            'data'    => $this->buildCaptureSuccessPayload($ordenId, $paypalOrderId, $pagoActualizado, $warning),
            'status'  => 200,
        ];
    }

    private function validarPagoSimulado(int $ordenId, array $data): array
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

        if (! $this->ordenService->tieneDetalles($ordenId)) {
            $errors['orden'] = 'La orden debe tener al menos un detalle.';
        }

        $estado = sanitize_simple_string($data['estado'] ?? '');

        if (! in_array($estado, ['aprobado', 'rechazado', 'cancelado'], true)) {
            $errors['estado'] = 'El estado del pago simulado es invalido.';
        }

        return $errors;
    }

    private function mapearEstadoOrden(string $estadoPago): string
    {
        return match ($estadoPago) {
            'aprobado' => 'pagada',
            'rechazado' => 'fallida',
            'cancelado' => 'cancelada',
            default => 'pendiente',
        };
    }

    private function obtenerPagoPaypalPendientePorOrden(int $ordenId): ?array
    {
        $pago = $this->pagoModel
            ->where('orden_id', $ordenId)
            ->where('proveedor', 'paypal')
            ->where('estado', 'pendiente')
            ->orderBy('id', 'DESC')
            ->first();

        return $pago ?: null;
    }

    private function obtenerPagoPaypalPorOrdenYReferencia(int $ordenId, string $paypalOrderId): ?array
    {
        $pago = $this->pagoModel
            ->where('orden_id', $ordenId)
            ->where('proveedor', 'paypal')
            ->where('referencia_pago', $paypalOrderId)
            ->orderBy('id', 'DESC')
            ->first();

        return $pago ?: null;
    }

    private function validarOrdenPaypal(array $paypalOrder, array $orden, array $pago, string $moneda): array
    {
        $purchaseUnit = $paypalOrder['purchase_units'][0] ?? [];
        $amount = $purchaseUnit['amount'] ?? [];
        $errors = [];

        if (($paypalOrder['id'] ?? '') !== $pago['referencia_pago']) {
            $errors['paypal_order_id'] = 'La referencia de PayPal no coincide con la registrada.';
        }

        if (strtoupper((string) ($amount['currency_code'] ?? '')) !== strtoupper($moneda)) {
            $errors['moneda'] = 'La moneda de PayPal no coincide con la orden local.';
        }

        if ((float) ($amount['value'] ?? 0) !== (float) $orden['total']) {
            $errors['monto'] = 'El monto de PayPal no coincide con el total de la orden.';
        }

        return $errors;
    }

    private function validarCapturaPaypal(array $captureResponse, array $orden, array $pago, string $moneda): array
    {
        $purchaseUnit = $captureResponse['purchase_units'][0] ?? [];
        $capture = $purchaseUnit['payments']['captures'][0] ?? [];
        $amount = $capture['amount'] ?? $purchaseUnit['amount'] ?? [];
        $errors = [];

        if (! in_array(strtoupper((string) ($captureResponse['status'] ?? '')), ['COMPLETED'], true)) {
            $errors['estado'] = 'PayPal no devolvio una captura completada.';
        }

        if (($captureResponse['id'] ?? '') !== $pago['referencia_pago']) {
            $errors['paypal_order_id'] = 'La referencia PayPal no coincide con la orden registrada.';
        }

        if (strtoupper((string) ($amount['currency_code'] ?? '')) !== strtoupper($moneda)) {
            $errors['moneda'] = 'La moneda capturada en PayPal no coincide con la orden local.';
        }

        if ((float) ($amount['value'] ?? 0) !== (float) $orden['total']) {
            $errors['monto'] = 'El monto capturado en PayPal no coincide con el total de la orden.';
        }

        return $errors;
    }

    private function registrarIntentoPaypalFallido(int $ordenId, array $pago, array $response, string $estado): void
    {
        $this->pagoModel->update((int) $pago['id'], [
            'estado'         => $estado,
            'respuesta_json' => json_encode($this->payPalService->sanitizarRespuesta($response), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at'     => app_timestamp(),
        ]);

        $this->bitacoraService->registrar([
            'usuario_id'   => get_authenticated_user()['id'] ?? null,
            'modulo'       => 'pagos',
            'accion'       => 'paypal_warning',
            'descripcion'  => 'Intento de captura PayPal no aprobado',
            'datos_nuevos' => [
                'orden_id' => $ordenId,
                'estado'   => $estado,
            ],
        ]);
    }

    private function buildCaptureSuccessPayload(int $ordenId, string $paypalOrderId, array $pago, ?string $warning): array
    {
        $orden = $this->ordenService->obtenerPorIdAdminEnriquecida($ordenId);
        $boletos = $orden['boletos_generados'] ?? [];

        return [
            'orden' => [
                'id'     => $orden['id'] ?? $ordenId,
                'folio'  => $orden['folio'] ?? null,
                'estado' => $orden['estado'] ?? null,
                'total'  => (float) ($orden['total'] ?? 0),
            ],
            'pago' => [
                'paypal_order_id' => $paypalOrderId,
                'referencia_pago' => $paypalOrderId,
                'estado'          => $pago['estado'] ?? 'aprobado',
                'moneda'          => $pago['moneda'] ?? $this->obtenerMoneda(),
                'monto'           => (float) ($pago['monto'] ?? 0),
            ],
            'boletos' => [
                'cantidad_generados' => count($boletos),
            ],
            'correo' => [
                'enviado' => $warning === null,
            ],
            'warning' => $warning,
        ];
    }

    private function obtenerMoneda(): string
    {
        $config = $this->configuracionService->obtenerConfiguracion();
        $moneda = 'MXN';

        foreach (($config['sections']['general']['fields'] ?? []) as $field) {
            if (($field['key'] ?? '') === 'moneda' && ! empty($field['value'])) {
                $moneda = strtoupper((string) $field['value']);
                break;
            }
        }

        return $moneda;
    }
}
