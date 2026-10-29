<?php

namespace App\Controllers\Api;

use App\Services\CategoriaService;
use App\Services\EventoMetricaService;
use App\Services\EventoService;
use App\Services\SedeService;
use CodeIgniter\HTTP\Exceptions\HTTPException;
use CodeIgniter\HTTP\ResponseInterface;

class EventosController extends BaseApiController
{
    public function __construct(
        private readonly EventoService $eventoService = new EventoService(),
        private readonly EventoMetricaService $eventoMetricaService = new EventoMetricaService(),
        private readonly CategoriaService $categoriaService = new CategoriaService(),
        private readonly SedeService $sedeService = new SedeService()
    ) {
    }

    public function index(): ResponseInterface
    {
        return $this->respondSuccess(
            'Eventos obtenidos correctamente',
            $this->eventoService->obtenerPublicados()
        );
    }

    public function adminIndex(): ResponseInterface
    {
        return $this->respondSuccess(
            'Eventos administrativos obtenidos correctamente',
            $this->eventoService->obtenerTodosAdmin()
        );
    }

    public function show($id = null): ResponseInterface
    {
        $id = (int) $id;

        if ($id <= 0) {
            return $this->respondError(
                'El identificador del evento es invalido',
                ['id' => 'El id debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        $evento = $this->eventoService->obtenerPublicadoPorId($id);

        if ($evento === null) {
            return $this->respondError(
                'El evento solicitado no existe',
                ['id' => 'No se encontro un evento publicado con el id proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        return $this->respondSuccess('Evento obtenido correctamente', $evento);
    }

    public function destacados(): ResponseInterface
    {
        return $this->respondSuccess(
            'Eventos destacados obtenidos correctamente',
            $this->eventoMetricaService->obtenerDestacados()
        );
    }

    public function registrarInteraccion($id = null): ResponseInterface
    {
        $id = (int) $id;

        if ($id <= 0) {
            return $this->respondError(
                'El identificador del evento es invalido',
                ['id' => 'El id debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        $data = $this->requestData();

        if (! $this->validateData($data, ['tipo' => 'required|in_list[vista,click,busqueda,compra]'])) {
            return $this->respondError(
                'Error de validacion al registrar la interaccion',
                $this->validator->getErrors(),
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (! $this->eventoMetricaService->eventoExiste($id)) {
            return $this->respondError(
                'El evento solicitado no existe',
                ['id' => 'No se encontro un evento con el id proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        $metrica = $this->eventoMetricaService->registrarInteraccion($id, (string) ($data['tipo'] ?? ''));

        if ($metrica === null) {
            return $this->respondError(
                'No fue posible registrar la interaccion del evento',
                ['interaccion' => 'La interaccion no pudo ser procesada.'],
                ResponseInterface::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->respondSuccess('Interaccion registrada correctamente', $metrica);
    }

    public function store(): ResponseInterface
    {
        $data = $this->requestData();

        $rules = $this->validationRules();

        if (! $this->validateData($data, $rules)) {
            return $this->respondError(
                'Error de validacion al crear el evento',
                $this->validator->getErrors(),
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if ($relationErrors = $this->validateRelations($data)) {
            return $this->respondError(
                'Error de validacion al crear el evento',
                $relationErrors,
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $evento = $this->eventoService->crear($data);

        if ($evento === null) {
            return $this->respondError(
                'No fue posible crear el evento',
                ['evento' => 'El evento no pudo ser creado.'],
                ResponseInterface::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->response
            ->setStatusCode(ResponseInterface::HTTP_CREATED)
            ->setJSON([
                'success' => true,
                'message' => 'Evento creado correctamente',
                'data'    => $evento,
                'errors'  => null,
            ]);
    }

    public function update($id = null): ResponseInterface
    {
        $id = (int) $id;

        if ($id <= 0) {
            return $this->respondError(
                'El identificador del evento es invalido',
                ['id' => 'El id debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        $data = $this->requestData(true);

        if (! $this->validateData($data, $this->validationRules())) {
            return $this->respondError(
                'Error de validacion al actualizar el evento',
                $this->validator->getErrors(),
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if ($relationErrors = $this->validateRelations($data)) {
            return $this->respondError(
                'Error de validacion al actualizar el evento',
                $relationErrors,
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if ($this->eventoService->obtenerPorIdAdmin($id) === null) {
            return $this->respondError(
                'El evento solicitado no existe',
                ['id' => 'No se encontro un evento con el id proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        $evento = $this->eventoService->actualizar($id, $data);

        if ($evento === null) {
            return $this->respondError(
                'No fue posible actualizar el evento',
                ['evento' => 'El evento no pudo ser actualizado.'],
                ResponseInterface::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->respondSuccess('Evento actualizado correctamente', $evento);
    }

    public function delete($id = null): ResponseInterface
    {
        $id = (int) $id;

        if ($id <= 0) {
            return $this->respondError(
                'El identificador del evento es invalido',
                ['id' => 'El id debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        if ($this->eventoService->obtenerPorIdAdmin($id) === null) {
            return $this->respondError(
                'El evento solicitado no existe',
                ['id' => 'No se encontro un evento con el id proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        if (! $this->eventoService->eliminar($id)) {
            return $this->respondError(
                'No fue posible eliminar el evento',
                ['evento' => 'El evento no pudo ser eliminado.'],
                ResponseInterface::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->respondSuccess('Evento eliminado correctamente', null);
    }

    private function validationRules(): array
    {
        return [
            'categoria_id' => 'required|integer|greater_than[0]',
            'sede_id'      => 'required|integer|greater_than[0]',
            'titulo'       => 'required|min_length[2]|max_length[255]',
            'lugar'        => 'required|min_length[2]|max_length[255]',
            'fecha_inicio' => 'required|max_length[255]',
            'fecha_fin'    => 'required|max_length[255]',
        ];
    }

    private function validateRelations(array $data): array
    {
        $errors = [];

        $categoriaId = (int) ($data['categoria_id'] ?? 0);
        $sedeId = (int) ($data['sede_id'] ?? 0);

        if ($categoriaId > 0 && $this->categoriaService->obtenerPorId($categoriaId) === null) {
            $errors['categoria_id'] = 'La categoria seleccionada no existe.';
        }

        if ($sedeId > 0 && $this->sedeService->obtenerPorId($sedeId) === null) {
            $errors['sede_id'] = 'La sede seleccionada no existe.';
        }

        return $errors;
    }

    private function requestData(bool $includeRawInput = false): array
    {
        try {
            $json = $this->request->getJSON(true);

            if (is_array($json) && $json !== []) {
                return $json;
            }
        } catch (HTTPException) {
        }

        $post = $this->request->getPost();

        if (is_array($post) && $post !== []) {
            return $post;
        }

        return $includeRawInput ? ($this->request->getRawInput() ?? []) : [];
    }
}
