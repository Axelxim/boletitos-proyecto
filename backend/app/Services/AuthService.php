<?php

namespace App\Services;

use App\Models\RolModel;
use CodeIgniter\I18n\Time;

class AuthService
{
    private const TOKEN_TYPE = 'provisional';

    public function __construct(
        private readonly UsuarioService $usuarioService = new UsuarioService(),
        private readonly BitacoraService $bitacoraService = new BitacoraService(),
        private readonly RolModel $rolModel = new RolModel(),
        private readonly CorreoVerificacionService $correoVerificacionService = new CorreoVerificacionService()
    ) {
    }

    public function registrar(array $data): array
    {
        $correo = strtolower(sanitize_simple_string($data['correo'] ?? ''));

        if ($this->usuarioService->obtenerPorCorreo($correo) !== null) {
            return [
                'success' => false,
                'message' => 'El correo ya se encuentra registrado',
                'errors'  => ['correo' => 'El correo debe ser unico.'],
                'status'  => 422,
            ];
        }

        $rolUsuario = $this->rolModel->where('nombre', 'usuario')->first();

        if ($rolUsuario === null) {
            return [
                'success' => false,
                'message' => 'No existe el rol por defecto para registro',
                'errors'  => ['rol' => 'No se encontro el rol usuario.'],
                'status'  => 500,
            ];
        }

        $usuario = $this->usuarioService->crear([
            'rol_id'     => $rolUsuario['id'],
            'nombre'     => sanitize_simple_string($data['nombre'] ?? ''),
            'apellido'   => sanitize_simple_string($data['apellido'] ?? ''),
            'correo'     => $correo,
            'password'   => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            'telefono'   => sanitize_simple_string($data['telefono'] ?? ''),
            'estado'     => $data['estado'] ?? 'activo',
            'correo_verificado_at' => null,
            'created_at' => app_timestamp(),
            'updated_at' => app_timestamp(),
        ]);

        if ($usuario === null) {
            return [
                'success' => false,
                'message' => 'No fue posible registrar el usuario',
                'errors'  => ['usuario' => 'La insercion no pudo completarse.'],
                'status'  => 500,
            ];
        }

        $correo = $this->correoVerificacionService->generarYEnviar($usuario);

        $this->bitacoraService->registrar([
            'usuario_id'  => $usuario['id'],
            'modulo'      => 'auth',
            'accion'      => 'register',
            'descripcion' => 'Registro de usuario exitoso',
            'datos_nuevos' => [
                'usuario_id' => $usuario['id'],
                'correo'     => $usuario['correo'],
            ],
        ]);

        return [
            'success' => true,
            'message' => ($correo['success'] ?? false)
                ? 'Usuario registrado correctamente. Revisa tu correo para confirmar tu cuenta'
                : 'Usuario registrado correctamente, pero no fue posible enviar el correo de verificacion',
            'data'    => [
                'usuario' => $usuario,
                'verification_email_sent' => (bool) ($correo['success'] ?? false),
            ],
            'status'  => 201,
        ];
    }

    public function login(string $correo, string $password): array
    {
        $usuario = $this->usuarioService->obtenerPorCorreo(strtolower(sanitize_simple_string($correo)));

        if ($usuario === null || ! isset($usuario['password']) || ! password_verify($password, $usuario['password'])) {
            $this->bitacoraService->registrar([
                'usuario_id'  => $usuario['id'] ?? null,
                'modulo'      => 'auth',
                'accion'      => 'login_failed',
                'descripcion' => 'Intento de inicio de sesion fallido',
                'datos_nuevos' => [
                    'correo' => strtolower(sanitize_simple_string($correo)),
                ],
            ]);

            return [
                'success' => false,
                'message' => 'Credenciales invalidas',
                'errors'  => ['correo' => 'El correo o password son incorrectos.'],
                'status'  => 401,
            ];
        }

        if (! $this->usuarioEstaActivo($usuario['estado'] ?? null)) {
            $this->bitacoraService->registrar([
                'usuario_id'  => $usuario['id'] ?? null,
                'modulo'      => 'auth',
                'accion'      => 'login_failed',
                'descripcion' => 'Intento de inicio de sesion con usuario inactivo',
                'datos_nuevos' => [
                    'correo' => strtolower(sanitize_simple_string($correo)),
                    'estado' => $usuario['estado'] ?? null,
                ],
            ]);

            return [
                'success' => false,
                'message' => 'El usuario no se encuentra activo',
                'errors'  => ['estado' => 'El usuario no tiene acceso actualmente.'],
                'status'  => 403,
            ];
        }

        if ($this->requiereCorreoVerificado($usuario) && empty($usuario['correo_verificado_at'])) {
            $this->bitacoraService->registrar([
                'usuario_id'  => $usuario['id'] ?? null,
                'modulo'      => 'auth',
                'accion'      => 'login_failed',
                'descripcion' => 'Intento de inicio de sesion con correo no verificado',
                'datos_nuevos' => [
                    'correo' => strtolower(sanitize_simple_string($correo)),
                ],
            ]);

            return [
                'success' => false,
                'message' => 'Debes confirmar tu correo antes de iniciar sesion',
                'errors'  => ['correo' => 'La cuenta aun no ha sido verificada.'],
                'status'  => 403,
            ];
        }

        $usuarioResponse = $this->usuarioService->transformarUsuario($usuario);
        $token = $this->generarTokenProvisional($usuarioResponse);

        $this->bitacoraService->registrar([
            'usuario_id'  => $usuarioResponse['id'],
            'modulo'      => 'auth',
            'accion'      => 'login',
            'descripcion' => 'Inicio de sesion exitoso',
            'datos_nuevos' => [
                'usuario_id' => $usuarioResponse['id'],
                'correo'     => $usuarioResponse['correo'],
            ],
        ]);

        return [
            'success' => true,
            'message' => 'Inicio de sesion correcto',
            'data'    => [
                'usuario' => $usuarioResponse,
                'token'   => $token,
            ],
            'status'  => 200,
        ];
    }

    private function usuarioEstaActivo(mixed $estado): bool
    {
        $estado = is_string($estado) ? strtolower(trim($estado)) : $estado;

        return in_array($estado, [1, '1', true, 'activo', 'active'], true);
    }

    private function requiereCorreoVerificado(array $usuario): bool
    {
        return strtolower((string) ($usuario['rol_nombre'] ?? $usuario['rol']['nombre'] ?? '')) !== 'admin';
    }

    private function generarTokenProvisional(array $usuario): array
    {
        $expiresAt = Time::now(config(\Config\App::class)->appTimezone)->addHours(1)->format('Y-m-d H:i:s');
        $tokenValue = $this->firmarTokenProvisional([
            'type'       => self::TOKEN_TYPE,
            'user_id'    => $usuario['id'],
            'expires_at' => $expiresAt,
        ]);

        return [
            'type'       => self::TOKEN_TYPE,
            'value'      => $tokenValue,
            'expires_at' => $expiresAt,
            'note'       => 'Token temporal mientras se implementa JWT.',
            'user_id'    => $usuario['id'],
        ];
    }

    public function validarTokenProvisional(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 2) {
            return null;
        }

        [$encodedPayload, $signature] = $parts;
        $expectedSignature = hash_hmac('sha256', $encodedPayload, $this->obtenerLlaveToken());

        if (! hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $decodedPayload = base64_decode(strtr($this->normalizarBase64($encodedPayload), '-_', '+/'), true);

        if ($decodedPayload === false) {
            return null;
        }

        $payload = json_decode($decodedPayload, true);

        if (! is_array($payload) || ($payload['type'] ?? null) !== self::TOKEN_TYPE) {
            return null;
        }

        $userId = $payload['user_id'] ?? null;

        if (! is_numeric($userId) || (int) $userId <= 0) {
            return null;
        }

        $expiresAt = $payload['expires_at'] ?? null;

        if (! is_string($expiresAt) || strtotime($expiresAt) === false || strtotime($expiresAt) < time()) {
            return null;
        }

        return $payload;
    }

    public function verificarCorreo(string $token): array
    {
        return $this->correoVerificacionService->verificarToken($token);
    }

    public function reenviarVerificacion(string $correo): array
    {
        return $this->correoVerificacionService->reenviarPorCorreo($correo);
    }

    private function normalizarBase64(string $value): string
    {
        $padding = strlen($value) % 4;

        if ($padding === 0) {
            return $value;
        }

        return $value . str_repeat('=', 4 - $padding);
    }

    private function firmarTokenProvisional(array $payload): string
    {
        $encodedPayload = rtrim(strtr(base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $encodedPayload, $this->obtenerLlaveToken());

        return $encodedPayload . '.' . $signature;
    }

    private function obtenerLlaveToken(): string
    {
        $appConfig = config(\Config\App::class);

        return hash('sha256', APP_NAMESPACE . '|' . $appConfig->baseURL . '|boletitos-provisional-token');
    }
}
