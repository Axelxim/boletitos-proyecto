<?php

namespace App\Controllers\Api;

use App\Services\TipoBoletoService;
use CodeIgniter\HTTP\ResponseInterface;

class TiposBoletosController extends BaseApiController
{
    public function __construct(private readonly TipoBoletoService $tipoBoletoService = new TipoBoletoService())
    {
    }

    public function publicIndex($eventoId = null): ResponseInterface
    {
        $eventoId = (int) $eventoId;

        if ($eventoId <= 0) {
            return $this->respondError(
                'El identificador del evento es invalido',
                ['evento_id' => 'El id del evento debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        if (! $this->tipoBoletoService->eventoExiste($eventoId)) {
            return $this->respondError(
                'El evento solicitado no existe',
                ['evento_id' => 'No se encontro un evento con el id proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        return $this->respondSuccess(
            'Tipos de boletos obtenidos correctamente',
            $this->tipoBoletoService->obtenerPublicosPorEvento($eventoId)
        );
    }

    public function index(): ResponseInterface
    {
        return $this->respondSuccess(
            'Tipos de boletos obtenidos correctamente',
            $this->tipoBoletoService->obtenerTodosAdmin()
        );
    }

    public function show($id = null): ResponseInterface
    {
        $id = (int) $id;

        if ($id <= 0) {
            return $this->respondError(
                'El identificador del tipo de boleto es invalido',
                ['id' => 'El id debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        $tipo = $this->tipoBoletoService->obtenerPorIdAdmin($id);

        if ($tipo === null) {
            return $this->respondError(
                'El tipo de boleto solicitado no existe',
                ['id' => 'No se encontro un tipo de boleto con el id proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        return $this->respondSuccess('Tipo de boleto obtenido correctamente', $tipo);
    }

    public function store(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        if ($validationErrors = $this->validatePayload($data, 'crear')) {
            return $this->respondError(
                'Error de validacion al crear el tipo de boleto',
                $validationErrors,
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $tipo = $this->tipoBoletoService->crear($data);

        if ($tipo === null) {
            return $this->respondError(
                'No fue posible crear el tipo de boleto',
                ['tipo_boleto' => 'El tipo de boleto no pudo ser creado.'],
                ResponseInterface::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->response
            ->setStatusCode(ResponseInterface::HTTP_CREATED)
            ->setJSON([
                'success' => true,
                'message' => 'Tipo de boleto creado correctamente',
                'data'    => $tipo,
                'errors'  => null,
            ]);
    }

    public function update($id = null): ResponseInterface
    {
        $id = (int) $id;

        if ($id <= 0) {
            return $this->respondError(
                'El identificador del tipo de boleto es invalido',
                ['id' => 'El id debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        $data = $this->request->getJSON(true) ?? $this->request->getRawInput();

        if ($validationErrors = $this->validatePayload($data, 'actualizar')) {
            return $this->respondError(
                'Error de validacion al actualizar el tipo de boleto',
                $validationErrors,
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if ($this->tipoBoletoService->obtenerPorIdAdmin($id) === null) {
            return $this->respondError(
                'El tipo de boleto solicitado no existe',
                ['id' => 'No se encontro un tipo de boleto con el id proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        $tipo = $this->tipoBoletoService->actualizar($id, $data);

        if ($tipo === null) {
            return $this->respondError(
                'No fue posible actualizar el tipo de boleto',
                ['tipo_boleto' => 'El tipo de boleto no pudo ser actualizado.'],
                ResponseInterface::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->respondSuccess('Tipo de boleto actualizado correctamente', $tipo);
    }

    public function delete($id = null): ResponseInterface
    {
        $id = (int) $id;

        if ($id <= 0) {
            return $this->respondError(
                'El identificador del tipo de boleto es invalido',
                ['id' => 'El id debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        if ($this->tipoBoletoService->obtenerPorIdAdmin($id) === null) {
            return $this->respondError(
                'El tipo de boleto solicitado no existe',
                ['id' => 'No se encontro un tipo de boleto con el id proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        if (! $this->tipoBoletoService->eliminar($id)) {
            return $this->respondError(
                'No fue posible eliminar el tipo de boleto',
                ['tipo_boleto' => 'El tipo de boleto no pudo ser eliminado.'],
                ResponseInterface::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->respondSuccess('Tipo de boleto eliminado correctamente', null);
    }

    private function validatePayload(array $data, string $context): array
    {
        $rules = [
            'evento_id'           => 'required|integer|greater_than[0]',
            'nombre'              => 'required|min_length[2]|max_length[255]',
            'precio'              => 'required|decimal',
            'cantidad_total'      => 'required|integer|greater_than_equal_to[0]',
            'cantidad_disponible' => 'required|integer|greater_than_equal_to[0]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validator->getErrors();
        }

        $errors = [];
        $eventoId = (int) ($data['evento_id'] ?? 0);
        $precio = (float) ($data['precio'] ?? 0);
        $cantidadTotal = (int) ($data['cantidad_total'] ?? 0);
        $cantidadDisponible = (int) ($data['cantidad_disponible'] ?? 0);

        if (! $this->tipoBoletoService->eventoExiste($eventoId)) {
            $errors['evento_id'] = 'El evento seleccionado no existe.';
        }

        if ($precio < 0) {
            $errors['precio'] = 'El precio no puede ser negativo.';
        }

        if ($cantidadTotal < 0) {
            $errors['cantidad_total'] = 'La cantidad total no puede ser negativa.';
        }

        if ($cantidadDisponible < 0) {
            $errors['cantidad_disponible'] = 'La cantidad disponible no puede ser negativa.';
        }

        if ($cantidadDisponible > $cantidadTotal) {
            $errors['cantidad_disponible'] = 'La cantidad disponible no puede ser mayor que la cantidad total.';
        }

        return $errors;
    }
}
