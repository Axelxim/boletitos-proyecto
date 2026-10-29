<?php

namespace App\Controllers\Api;

use App\Services\PerfilService;
use CodeIgniter\HTTP\ResponseInterface;

class PerfilController extends BaseApiController
{
    public function __construct(private readonly PerfilService $perfilService = new PerfilService())
    {
    }

    public function show(): ResponseInterface
    {
        $usuario = get_authenticated_user();

        if ($usuario === null) {
            return $this->respondError(
                'No fue posible obtener el usuario autenticado.',
                ['usuario' => 'No existe sesion activa.'],
                ResponseInterface::HTTP_UNAUTHORIZED
            );
        }

        $perfil = $this->perfilService->obtenerPerfil((int) $usuario['id']);

        if ($perfil === null) {
            return $this->respondError(
                'No se encontro el perfil del usuario.',
                ['usuario' => 'No se encontro el usuario autenticado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        return $this->respondSuccess('Perfil obtenido correctamente.', $perfil);
    }

    public function update(): ResponseInterface
    {
        $usuario = get_authenticated_user();

        if ($usuario === null) {
            return $this->respondError(
                'No fue posible obtener el usuario autenticado.',
                ['usuario' => 'No existe sesion activa.'],
                ResponseInterface::HTTP_UNAUTHORIZED
            );
        }

        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $rules = [
            'nombre'   => 'required|min_length[2]|max_length[255]',
            'apellido' => 'permit_empty|max_length[255]',
            'telefono' => 'permit_empty|regex_match[/^[0-9+\-\s()]{7,20}$/]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->respondError(
                'Error de validacion al actualizar el perfil',
                $this->validator->getErrors(),
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $result = $this->perfilService->actualizarPerfil((int) $usuario['id'], $data);

        if (! $result['success']) {
            return $this->respondError($result['message'], $result['errors'], $result['status']);
        }

        return $this->respondSuccess($result['message'], $result['data']);
    }

    public function updatePassword(): ResponseInterface
    {
        $usuario = get_authenticated_user();

        if ($usuario === null) {
            return $this->respondError(
                'No fue posible obtener el usuario autenticado.',
                ['usuario' => 'No existe sesion activa.'],
                ResponseInterface::HTTP_UNAUTHORIZED
            );
        }

        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $rules = [
            'password_actual'       => 'required|min_length[8]|max_length[255]',
            'password_nuevo'        => 'required|min_length[8]|max_length[255]',
            'password_confirmacion' => 'required|matches[password_nuevo]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->respondError(
                'Error de validacion al cambiar la password',
                $this->validator->getErrors(),
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $result = $this->perfilService->cambiarPassword(
            (int) $usuario['id'],
            (string) $data['password_actual'],
            (string) $data['password_nuevo']
        );

        if (! $result['success']) {
            return $this->respondError($result['message'], $result['errors'], $result['status']);
        }

        return $this->respondSuccess($result['message'], $result['data']);
    }
}
