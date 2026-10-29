<?php

namespace App\Controllers\Api;

use App\Services\CategoriaService;
use CodeIgniter\HTTP\ResponseInterface;

class CategoriasController extends BaseApiController
{
    public function __construct(private readonly CategoriaService $categoriaService = new CategoriaService())
    {
    }

    public function index(): ResponseInterface
    {
        return $this->respondSuccess(
            'Categorias obtenidas correctamente',
            $this->categoriaService->obtenerTodas()
        );
    }

    public function show($id = null): ResponseInterface
    {
        $id = (int) $id;

        if ($id <= 0) {
            return $this->respondError(
                'El identificador de la categoria es invalido',
                ['id' => 'El id debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        $categoria = $this->categoriaService->obtenerPorId($id);

        if ($categoria === null) {
            return $this->respondError(
                'La categoria solicitada no existe',
                ['id' => 'No se encontro una categoria con el id proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        return $this->respondSuccess('Categoria obtenida correctamente', $categoria);
    }

    public function store(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        $rules = [
            'nombre' => 'required|min_length[2]|max_length[255]',
            'estado' => 'permit_empty|max_length[50]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->respondError(
                'Error de validacion al crear la categoria',
                $this->validator->getErrors(),
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $categoria = $this->categoriaService->crear($data);

        if ($categoria === null) {
            return $this->respondError(
                'No fue posible crear la categoria',
                ['categoria' => 'La categoria no pudo ser creada.'],
                ResponseInterface::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->response
            ->setStatusCode(ResponseInterface::HTTP_CREATED)
            ->setJSON([
                'success' => true,
                'message' => 'Categoria creada correctamente',
                'data'    => $categoria,
                'errors'  => null,
            ]);
    }

    public function update($id = null): ResponseInterface
    {
        $id = (int) $id;

        if ($id <= 0) {
            return $this->respondError(
                'El identificador de la categoria es invalido',
                ['id' => 'El id debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        $data = $this->request->getJSON(true) ?? $this->request->getRawInput();

        $rules = [
            'nombre' => 'required|min_length[2]|max_length[255]',
            'estado' => 'permit_empty|max_length[50]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->respondError(
                'Error de validacion al actualizar la categoria',
                $this->validator->getErrors(),
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if ($this->categoriaService->obtenerPorId($id) === null) {
            return $this->respondError(
                'La categoria solicitada no existe',
                ['id' => 'No se encontro una categoria con el id proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        $categoria = $this->categoriaService->actualizar($id, $data);

        if ($categoria === null) {
            return $this->respondError(
                'No fue posible actualizar la categoria',
                ['categoria' => 'La categoria no pudo ser actualizada.'],
                ResponseInterface::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->respondSuccess('Categoria actualizada correctamente', $categoria);
    }

    public function delete($id = null): ResponseInterface
    {
        $id = (int) $id;

        if ($id <= 0) {
            return $this->respondError(
                'El identificador de la categoria es invalido',
                ['id' => 'El id debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        if ($this->categoriaService->obtenerPorId($id) === null) {
            return $this->respondError(
                'La categoria solicitada no existe',
                ['id' => 'No se encontro una categoria con el id proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        if (! $this->categoriaService->eliminar($id)) {
            return $this->respondError(
                'No fue posible eliminar la categoria',
                ['categoria' => 'La categoria no pudo ser eliminada.'],
                ResponseInterface::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->respondSuccess('Categoria eliminada correctamente', null);
    }
}
