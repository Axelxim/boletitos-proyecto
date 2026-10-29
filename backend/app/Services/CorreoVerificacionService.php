<?php

namespace App\Services;

use App\Models\TokenRecuperacionModel;
use Config\Services;
use Throwable;

class CorreoVerificacionService
{
    private const TOKEN_TYPE = 'verificacion_correo';
    private const TOKEN_EXPIRATION_HOURS = 24;

    public function __construct(
        private readonly TokenRecuperacionModel $tokenModel = new TokenRecuperacionModel(),
        private readonly UsuarioService $usuarioService = new UsuarioService(),
        private readonly BitacoraService $bitacoraService = new BitacoraService()
    ) {
    }

    public function generarYEnviar(array $usuario, bool $esReenvio = false): array
    {
        $this->marcarTokensPreviosComoUsados((int) $usuario['id']);

        $token = bin2hex(random_bytes(32));
        $expiracion = date('Y-m-d H:i:s', strtotime('+' . self::TOKEN_EXPIRATION_HOURS . ' hours'));

        $inserted = $this->tokenModel->insert([
            'usuario_id'  => $usuario['id'],
            'token'       => hash('sha256', $token),
            'tipo'        => self::TOKEN_TYPE,
            'usado'       => 0,
            'expiracion'  => $expiracion,
            'created_at'  => app_timestamp(),
        ]);

        if ($inserted === false) {
            return [
                'success' => false,
                'message' => 'No fue posible generar el token de verificacion.',
            ];
        }

        return $this->enviarCorreoVerificacion($usuario, $token, $expiracion, $esReenvio);
    }

    public function verificarToken(string $token): array
    {
        $registro = $this->tokenModel
            ->where('token', hash('sha256', $token))
            ->where('tipo', self::TOKEN_TYPE)
            ->first();

        if ($registro === null) {
            return [
                'success' => false,
                'message' => 'El token de verificacion no es valido.',
                'errors'  => ['token' => 'El token proporcionado no existe.'],
                'status'  => 404,
            ];
        }

        if ((int) ($registro['usado'] ?? 0) === 1) {
            return [
                'success' => false,
                'message' => 'El token de verificacion ya fue utilizado.',
                'errors'  => ['token' => 'El token ya fue usado.'],
                'status'  => 409,
            ];
        }

        if (strtotime((string) ($registro['expiracion'] ?? '')) === false || strtotime((string) $registro['expiracion']) < time()) {
            return [
                'success' => false,
                'message' => 'El token de verificacion ya expiro.',
                'errors'  => ['token' => 'El token ya no es valido por expiracion.'],
                'status'  => 410,
            ];
        }

        $usuario = $this->usuarioService->obtenerPorId((int) $registro['usuario_id']);

        if ($usuario === null) {
            return [
                'success' => false,
                'message' => 'No fue posible verificar el correo.',
                'errors'  => ['usuario' => 'El usuario asociado ya no existe.'],
                'status'  => 404,
            ];
        }

        $updated = $this->usuarioService->actualizar((int) $usuario['id'], [
            'correo_verificado_at' => app_timestamp(),
            'updated_at'           => app_timestamp(),
        ]);

        if ($updated === null) {
            return [
                'success' => false,
                'message' => 'No fue posible activar la cuenta.',
                'errors'  => ['usuario' => 'No se pudo actualizar el usuario.'],
                'status'  => 500,
            ];
        }

        $this->tokenModel->update((int) $registro['id'], ['usado' => 1]);

        $this->bitacoraService->registrar([
            'usuario_id'   => $usuario['id'],
            'modulo'       => 'auth',
            'accion'       => 'verify_email',
            'descripcion'  => 'Correo verificado correctamente',
            'datos_nuevos' => [
                'usuario_id' => $usuario['id'],
                'correo'     => $usuario['correo'],
            ],
        ]);

        return [
            'success' => true,
            'message' => 'Correo verificado correctamente.',
            'data'    => $updated,
            'status'  => 200,
        ];
    }

    public function reenviarPorCorreo(string $correo): array
    {
        $respuestaGenerica = [
            'success' => true,
            'message' => 'Si el correo puede recibir verificacion, enviaremos un nuevo enlace.',
            'status'  => 200,
        ];

        $usuario = $this->usuarioService->obtenerPorCorreo(strtolower(sanitize_simple_string($correo)));

        if ($usuario === null) {
            return $respuestaGenerica;
        }

        if (($usuario['rol']['nombre'] ?? '') === 'admin') {
            return $respuestaGenerica;
        }

        if (! empty($usuario['correo_verificado_at'])) {
            return $respuestaGenerica;
        }

        $envio = $this->generarYEnviar($usuario, true);

        if (! ($envio['success'] ?? false)) {
            return [
                'success' => true,
                'message' => 'Si el correo puede recibir verificacion, enviaremos un nuevo enlace.',
                'status'  => 200,
            ];
        }

        return $respuestaGenerica;
    }

    private function enviarCorreoVerificacion(array $usuario, string $tokenPlano, string $expiracion, bool $esReenvio): array
    {
        $email = Services::email();
        $fromEmail = (string) config('Email')->fromEmail;
        $fromName = (string) config('Email')->fromName;

        if ($fromEmail === '') {
            log_message('error', 'No hay remitente configurado para correo de verificacion.');

            $this->bitacoraService->registrar([
                'usuario_id'   => $usuario['id'],
                'modulo'       => 'correos',
                'accion'       => 'error',
                'descripcion'  => 'No hay remitente configurado para verificacion de correo',
                'datos_nuevos' => [
                    'correo'  => $usuario['correo'],
                    'reenvio' => $esReenvio,
                ],
            ]);

            return [
                'success' => false,
                'message' => 'No fue posible enviar el correo de verificacion.',
            ];
        }

        $frontendUrl = rtrim((string) env('app.frontendURL', 'https://mediumblue-worm-371886.hostingersite.com'), '/');
        $verificationUrl = $frontendUrl . '/verificar-correo?token=' . urlencode($tokenPlano);

        $email->clear(true);
        $email->setFrom($fromEmail, $fromName ?: 'Boletitos');
        $email->setTo((string) $usuario['correo']);
        $email->setSubject($esReenvio ? 'Nuevo enlace de verificacion - Boletitos' : 'Confirma tu correo - Boletitos');
        $email->setMessage(view('emails/verificacion_correo', [
            'usuario'         => $usuario,
            'verificationUrl' => $verificationUrl,
            'expiracion'      => $expiracion,
        ]));

        try {
            $enviado = $email->send(false);
        } catch (Throwable $exception) {
            $enviado = false;
            log_message('error', 'Excepcion al enviar correo de verificacion para usuario {usuario_id}: {message}', [
                'usuario_id' => $usuario['id'],
                'message'    => $exception->getMessage(),
            ]);
        }

        if (! $enviado) {
            $debugMessage = (string) $email->printDebugger(['headers']);

            log_message('error', 'Error al enviar correo de verificacion para usuario {usuario_id}: {debug}', [
                'usuario_id' => $usuario['id'],
                'debug'      => $debugMessage,
            ]);

            $this->bitacoraService->registrar([
                'usuario_id'   => $usuario['id'],
                'modulo'       => 'correos',
                'accion'       => 'error',
                'descripcion'  => 'Fallo el envio del correo de verificacion',
                'datos_nuevos' => [
                    'correo'  => $usuario['correo'],
                    'reenvio' => $esReenvio,
                ],
            ]);

            return [
                'success' => false,
                'message' => 'No fue posible enviar el correo de verificacion.',
            ];
        }

        $this->bitacoraService->registrar([
            'usuario_id'   => $usuario['id'],
            'modulo'       => 'correos',
            'accion'       => $esReenvio ? 'resend_verification' : 'send_verification',
            'descripcion'  => $esReenvio
                ? 'Correo de verificacion reenviado correctamente'
                : 'Correo de verificacion enviado correctamente',
            'datos_nuevos' => [
                'correo'     => $usuario['correo'],
                'reenvio'    => $esReenvio,
                'expiracion' => $expiracion,
            ],
        ]);

        return [
            'success' => true,
            'message' => $esReenvio
                ? 'Correo de verificacion reenviado correctamente.'
                : 'Correo de verificacion enviado correctamente.',
        ];
    }

    private function marcarTokensPreviosComoUsados(int $usuarioId): void
    {
        $this->tokenModel
            ->where('usuario_id', $usuarioId)
            ->where('tipo', self::TOKEN_TYPE)
            ->set(['usado' => 1])
            ->update();
    }
}
