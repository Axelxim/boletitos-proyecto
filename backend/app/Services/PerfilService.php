<?php

namespace App\Services;

class PerfilService
{
    public function __construct(
        private readonly UsuarioService $usuarioService = new UsuarioService(),
        private readonly BitacoraService $bitacoraService = new BitacoraService()
    ) {
    }

    public function obtenerPerfil(int $usuarioId): ?array
    {
        return $this->usuarioService->obtenerPorId($usuarioId);
    }

    public function actualizarPerfil(int $usuarioId, array $data): array
    {
        $usuarioActual = $this->usuarioService->obtenerPorId($usuarioId);

        if ($usuarioActual === null) {
            return [
                'success' => false,
                'message' => 'El usuario autenticado no existe.',
                'errors'  => ['usuario' => 'No se encontro el usuario.'],
                'status'  => 404,
            ];
        }

        $actualizado = $this->usuarioService->actualizar($usuarioId, [
            'nombre'     => sanitize_simple_string($data['nombre'] ?? ''),
            'apellido'   => sanitize_simple_string($data['apellido'] ?? ''),
            'telefono'   => sanitize_simple_string($data['telefono'] ?? ''),
            'updated_at' => app_timestamp(),
        ]);

        if ($actualizado === null) {
            return [
                'success' => false,
                'message' => 'No fue posible actualizar el perfil.',
                'errors'  => ['perfil' => 'La actualizacion no pudo completarse.'],
                'status'  => 500,
            ];
        }

        $this->bitacoraService->registrar([
            'usuario_id'        => $usuarioId,
            'modulo'            => 'perfil',
            'accion'            => 'update',
            'descripcion'       => 'Perfil actualizado correctamente',
            'datos_anteriores'  => [
                'nombre'   => $usuarioActual['nombre'],
                'apellido' => $usuarioActual['apellido'],
                'telefono' => $usuarioActual['telefono'],
            ],
            'datos_nuevos'      => [
                'nombre'   => $actualizado['nombre'],
                'apellido' => $actualizado['apellido'],
                'telefono' => $actualizado['telefono'],
            ],
        ]);

        return [
            'success' => true,
            'message' => 'Perfil actualizado correctamente.',
            'data'    => $actualizado,
            'status'  => 200,
        ];
    }

    public function cambiarPassword(int $usuarioId, string $passwordActual, string $passwordNuevo): array
    {
        $usuario = $this->usuarioService->obtenerPorId($usuarioId);

        if ($usuario === null) {
            return [
                'success' => false,
                'message' => 'El usuario autenticado no existe.',
                'errors'  => ['usuario' => 'No se encontro el usuario.'],
                'status'  => 404,
            ];
        }

        $usuarioConPassword = $this->usuarioService->obtenerPorCorreo((string) $usuario['correo']);

        if ($usuarioConPassword === null || ! isset($usuarioConPassword['password']) || ! password_verify($passwordActual, (string) $usuarioConPassword['password'])) {
            return [
                'success' => false,
                'message' => 'La password actual es incorrecta.',
                'errors'  => ['password_actual' => 'La password actual no coincide.'],
                'status'  => 422,
            ];
        }

        $actualizado = $this->usuarioService->actualizar($usuarioId, [
            'password'   => password_hash($passwordNuevo, PASSWORD_DEFAULT),
            'updated_at' => app_timestamp(),
        ]);

        if ($actualizado === null) {
            return [
                'success' => false,
                'message' => 'No fue posible actualizar la password.',
                'errors'  => ['password_nuevo' => 'La actualizacion no pudo completarse.'],
                'status'  => 500,
            ];
        }

        $this->bitacoraService->registrar([
            'usuario_id'   => $usuarioId,
            'modulo'       => 'perfil',
            'accion'       => 'change_password',
            'descripcion'  => 'Password actualizada correctamente',
            'datos_nuevos' => [
                'usuario_id' => $usuarioId,
            ],
        ]);

        return [
            'success' => true,
            'message' => 'Password actualizada correctamente.',
            'data'    => [
                'usuario_id' => $usuarioId,
            ],
            'status'  => 200,
        ];
    }
}
