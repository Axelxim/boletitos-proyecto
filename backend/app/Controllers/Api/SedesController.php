<?php

namespace App\Controllers\Api;

use App\Services\SedeService;
use CodeIgniter\HTTP\ResponseInterface;

class SedesController extends BaseApiController
{
    public function __construct(private readonly SedeService $sedeService = new SedeService())
    {
    }

    public function index(): ResponseInterface
    {
        return $this->respondSuccess(
            'Sedes obtenidas correctamente',
            $this->sedeService->obtenerTodas()
        );
    }

    public function show($id = null): ResponseInterface
    {
        $id = (int) $id;

        if ($id <= 0) {
            return $this->respondError(
                'El identificador de la sede es invalido',
                ['id' => 'El id debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        $sede = $this->sedeService->obtenerPorId($id);

        if ($sede === null) {
            return $this->respondError(
                'La sede solicitada no existe',
                ['id' => 'No se encontro una sede con el id proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        return $this->respondSuccess('Sede obtenida correctamente', $sede);
    }

    public function store(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        $rules = [
            'nombre'    => 'required|min_length[2]|max_length[255]',
            'direccion' => 'required|min_length[5]|max_length[255]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->respondError(
                'Error de validacion al crear la sede',
                $this->validator->getErrors(),
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $sede = $this->sedeService->crear($data);

        if ($sede === null) {
            return $this->respondError(
                'No fue posible crear la sede',
                ['sede' => 'La sede no pudo ser creada.'],
                ResponseInterface::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->response
            ->setStatusCode(ResponseInterface::HTTP_CREATED)
            ->setJSON([
                'success' => true,
                'message' => 'Sede creada correctamente',
                'data'    => $sede,
                'errors'  => null,
            ]);
    }

    public function update($id = null): ResponseInterface
    {
        $id = (int) $id;

        if ($id <= 0) {
            return $this->respondError(
                'El identificador de la sede es invalido',
                ['id' => 'El id debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        $data = $this->request->getJSON(true) ?? $this->request->getRawInput();

        $rules = [
            'nombre'    => 'required|min_length[2]|max_length[255]',
            'direccion' => 'required|min_length[5]|max_length[255]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->respondError(
                'Error de validacion al actualizar la sede',
                $this->validator->getErrors(),
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if ($this->sedeService->obtenerPorId($id) === null) {
            return $this->respondError(
                'La sede solicitada no existe',
                ['id' => 'No se encontro una sede con el id proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        $sede = $this->sedeService->actualizar($id, $data);

        if ($sede === null) {
            return $this->respondError(
                'No fue posible actualizar la sede',
                ['sede' => 'La sede no pudo ser actualizada.'],
                ResponseInterface::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->respondSuccess('Sede actualizada correctamente', $sede);
    }

    public function delete($id = null): ResponseInterface
    {
        $id = (int) $id;

        if ($id <= 0) {
            return $this->respondError(
                'El identificador de la sede es invalido',
                ['id' => 'El id debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        if ($this->sedeService->obtenerPorId($id) === null) {
            return $this->respondError(
                'La sede solicitada no existe',
                ['id' => 'No se encontro una sede con el id proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        if (! $this->sedeService->eliminar($id)) {
            return $this->respondError(
                'No fue posible eliminar la sede',
                ['sede' => 'La sede no pudo ser eliminada.'],
                ResponseInterface::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->respondSuccess('Sede eliminada correctamente', null);
    }
}
