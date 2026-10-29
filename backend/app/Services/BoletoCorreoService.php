<?php

namespace App\Services;

use Config\Services;

class BoletoCorreoService
{
    public function __construct(
        private readonly OrdenService $ordenService = new OrdenService(),
        private readonly BitacoraService $bitacoraService = new BitacoraService()
    ) {
    }

    public function enviarCompraConfirmada(int $ordenId, bool $esReenvio = false): array
    {
        $orden = $this->ordenService->obtenerPorIdAdminEnriquecida($ordenId);

        if ($orden === null) {
            return [
                'success' => false,
                'message' => 'La orden seleccionada no existe.',
            ];
        }

        if (($orden['estado'] ?? '') !== 'pagada') {
            return [
                'success' => false,
                'message' => 'Solo se puede enviar correo para ordenes pagadas.',
            ];
        }

        if (empty($orden['boletos_generados'])) {
            return [
                'success' => false,
                'message' => 'La orden aun no tiene boletos generados.',
            ];
        }

        if (empty($orden['correo_comprador'])) {
            return [
                'success' => false,
                'message' => 'La orden no tiene correo del comprador.',
            ];
        }

        $email = Services::email();
        $fromEmail = (string) config('Email')->fromEmail;
        $fromName = (string) config('Email')->fromName;

        if ($fromEmail === '') {
            return [
                'success' => false,
                'message' => 'No hay correo remitente configurado en el entorno.',
            ];
        }

        $email->clear(true);
        $email->setFrom($fromEmail, $fromName ?: 'Boletitos');
        $email->setTo((string) $orden['correo_comprador']);
        $email->setSubject('Compra confirmada - ' . ($orden['folio'] ?? 'Boletitos'));
        $email->setMessage(view('emails/compra_confirmada', [
            'orden' => $orden,
        ]));

        $enviado = $email->send(false);

        if (! $enviado) {
            $debugMessage = (string) $email->printDebugger(['headers']);

            log_message('error', 'Error al enviar correo de boletos para la orden {orden_id}: {debug}', [
                'orden_id' => $ordenId,
                'debug'    => $debugMessage,
            ]);

            $this->bitacoraService->registrar([
                'usuario_id'   => $orden['usuario_id'] ?? null,
                'modulo'       => 'correos',
                'accion'       => 'error',
                'descripcion'  => 'Fallo el envio del correo de boletos',
                'datos_nuevos' => [
                    'orden_id' => $ordenId,
                    'reenvio'  => $esReenvio,
                    'debug'    => $debugMessage,
                ],
            ]);

            return [
                'success' => false,
                'message' => 'No fue posible enviar el correo al comprador.',
            ];
        }

        $this->bitacoraService->registrar([
            'usuario_id'   => $orden['usuario_id'] ?? null,
            'modulo'       => 'correos',
            'accion'       => $esReenvio ? 'resend' : 'send',
            'descripcion'  => $esReenvio
                ? 'Correo de boletos reenviado correctamente'
                : 'Correo de boletos enviado correctamente',
            'datos_nuevos' => [
                'orden_id' => $ordenId,
                'correo'   => $orden['correo_comprador'],
                'reenvio'  => $esReenvio,
            ],
        ]);

        return [
            'success' => true,
            'message' => $esReenvio
                ? 'Correo reenviado correctamente.'
                : 'Correo enviado correctamente.',
        ];
    }
}
