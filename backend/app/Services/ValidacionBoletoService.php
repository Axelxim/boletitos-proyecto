<?php

namespace App\Services;

use App\Models\ValidacionBoletoModel;

class ValidacionBoletoService
{
    public function __construct(
        private readonly ValidacionBoletoModel $validacionBoletoModel = new ValidacionBoletoModel(),
        private readonly BoletoService $boletoService = new BoletoService(),
        private readonly BitacoraService $bitacoraService = new BitacoraService()
    ) {
    }

    public function validarPorCodigoQr(string $codigoQr): array
    {
        $codigoQr = sanitize_simple_string($codigoQr);
        $boleto = $this->boletoService->obtenerPorCodigoQrOFolio($codigoQr);
        $usuarioValidadorId = get_authenticated_user()['id'] ?? null;

        if ($boleto === null) {
            $validacion = $this->registrarValidacion(null, $usuarioValidadorId, 'invalido', 'Codigo QR no encontrado');

            $this->bitacoraService->registrar([
                'usuario_id'   => $usuarioValidadorId,
                'modulo'       => 'validaciones_boletos',
                'accion'       => 'validate',
                'descripcion'  => 'Intento de validacion con boleto invalido',
                'datos_nuevos' => $validacion,
            ]);

            return [
                'success' => false,
                'message' => 'Boleto invalido',
                'data'    => [
                    'resultado'  => 'invalido',
                    'validacion' => $validacion,
                ],
            ];
        }

        $resultado = match ($boleto['estado']) {
            'vigente' => 'valido',
            'usado' => 'usado',
            'cancelado' => 'cancelado',
            default => 'invalido',
        };

        if ($resultado === 'valido') {
            $boleto = $this->boletoService->marcarComoUsado((int) $boleto['id']);
        }

        $observacion = match ($resultado) {
            'valido' => 'Boleto validado correctamente',
            'usado' => 'El boleto ya habia sido utilizado',
            'cancelado' => 'El boleto se encuentra cancelado',
            default => 'Estado de boleto no permitido',
        };

        $validacion = $this->registrarValidacion((int) $boleto['id'], $usuarioValidadorId, $resultado, $observacion);

        $this->bitacoraService->registrar([
            'usuario_id'   => $usuarioValidadorId,
            'modulo'       => 'validaciones_boletos',
            'accion'       => 'validate',
            'descripcion'  => 'Validacion de boleto registrada',
            'datos_nuevos' => [
                'boleto'     => $boleto,
                'validacion' => $validacion,
            ],
        ]);

        return [
            'success' => $resultado === 'valido',
            'message' => match ($resultado) {
                'valido' => 'Boleto valido',
                'usado' => 'Boleto ya usado',
                'cancelado' => 'Boleto cancelado',
                default => 'Boleto invalido',
            },
            'data' => [
                'resultado'  => $resultado,
                'boleto'     => $boleto,
                'validacion' => $validacion,
            ],
        ];
    }

    private function registrarValidacion(?int $boletoId, ?int $usuarioValidadorId, string $resultado, string $observacion): ?array
    {
        if ($boletoId === null) {
            return [
                'boleto_id'            => null,
                'usuario_validador_id' => $usuarioValidadorId,
                'resultado'            => $resultado,
                'observacion'          => $observacion,
                'fecha_validacion'     => app_timestamp(),
            ];
        }

        $inserted = $this->validacionBoletoModel->insert([
            'boleto_id'             => $boletoId,
            'usuario_validador_id'  => $usuarioValidadorId,
            'resultado'             => $resultado,
            'observacion'           => $observacion,
            'fecha_validacion'      => app_timestamp(),
        ]);

        if ($inserted === false) {
            return null;
        }

        return $this->validacionBoletoModel->find((int) $inserted);
    }
}
