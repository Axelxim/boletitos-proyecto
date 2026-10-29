<?php

namespace App\Controllers\Api;

use App\Services\UsuarioService;
use CodeIgniter\HTTP\ResponseInterface;

class UsuariosController extends BaseApiController
{
    public function __construct(private readonly UsuarioService $usuarioService = new UsuarioService())
    {
    }

    public function index(): ResponseInterface
    {
        return $this->respondSuccess(
            'Usuarios obtenidos correctamente',
            $this->usuarioService->obtenerTodos()
        );
    }

    public function show($id = null): ResponseInterface
    {
        $id = (int) $id;

        if ($id <= 0) {
            return $this->respondError(
                'El identificador del usuario es invalido',
                ['id' => 'El id debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        $usuario = $this->usuarioService->obtenerPorId($id);

        if ($usuario === null) {
            return $this->respondError(
                'El usuario solicitado no existe',
                ['id' => 'No se encontro un usuario con el id proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        return $this->respondSuccess('Usuario obtenido correctamente', $usuario);
    }
}
