<?php

namespace App\Controllers\Api;

use App\Services\RolService;
use CodeIgniter\HTTP\ResponseInterface;

class RolesController extends BaseApiController
{
    public function __construct(private readonly RolService $rolService = new RolService())
    {
    }

    public function index(): ResponseInterface
    {
        return $this->respondSuccess(
            'Roles obtenidos correctamente',
            $this->rolService->obtenerTodos()
        );
    }

    public function show($id = null): ResponseInterface
    {
        $id = (int) $id;

        if ($id <= 0) {
            return $this->respondError(
                'El identificador del rol es invalido',
                ['id' => 'El id debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        $rol = $this->rolService->obtenerPorId($id);

        if ($rol === null) {
            return $this->respondError(
                'El rol solicitado no existe',
                ['id' => 'No se encontro un rol con el id proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        return $this->respondSuccess('Rol obtenido correctamente', $rol);
    }
}
