<?php

namespace App\Controllers\Api;

use App\Services\EventoImagenService;
use CodeIgniter\HTTP\ResponseInterface;

class EventoImagenesController extends BaseApiController
{
    public function __construct(private readonly EventoImagenService $eventoImagenService = new EventoImagenService())
    {
    }

    public function index($eventoId = null): ResponseInterface
    {
        $eventoId = (int) $eventoId;

        if ($eventoId <= 0) {
            return $this->respondError(
                'El identificador del evento es invalido',
                ['evento_id' => 'El id del evento debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        if (! $this->eventoImagenService->eventoExiste($eventoId)) {
            return $this->respondError(
                'El evento solicitado no existe',
                ['evento_id' => 'No se encontro un evento con el id proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        return $this->respondSuccess(
            'Imagenes del evento obtenidas correctamente',
            $this->eventoImagenService->obtenerPublicasPorEvento($eventoId)
        );
    }

    public function store($eventoId = null): ResponseInterface
    {
        $eventoId = (int) $eventoId;

        if ($eventoId <= 0) {
            return $this->respondError(
                'El identificador del evento es invalido',
                ['evento_id' => 'El id del evento debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        if (! $this->eventoImagenService->eventoExiste($eventoId)) {
            return $this->respondError(
                'El evento solicitado no existe',
                ['evento_id' => 'No se encontro un evento con el id proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        $data = $this->request->getPost();
        $archivo = $this->request->getFile('imagen');

        if ($validationErrors = $this->validarCarga($eventoId, $data, $archivo)) {
            return $this->respondError(
                'Error de validacion al subir la imagen',
                $validationErrors,
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $imagen = $this->eventoImagenService->crear($eventoId, $archivo, $data);

        if ($imagen === null) {
            return $this->respondError(
                'No fue posible subir la imagen del evento',
                ['imagen' => 'La imagen no pudo ser guardada correctamente.'],
                ResponseInterface::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->response
            ->setStatusCode(ResponseInterface::HTTP_CREATED)
            ->setJSON([
                'success' => true,
                'message' => 'Imagen de evento creada correctamente',
                'data'    => $imagen,
                'errors'  => null,
            ]);
    }

    public function update($eventoId = null, $imagenId = null): ResponseInterface
    {
        $eventoId = (int) $eventoId;
        $imagenId = (int) $imagenId;

        if ($eventoId <= 0 || $imagenId <= 0) {
            return $this->respondError(
                'Los identificadores proporcionados son invalidos',
                [
                    'evento_id' => 'El id del evento debe ser un entero positivo.',
                    'imagen_id' => 'El id de la imagen debe ser un entero positivo.',
                ],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        if (! $this->eventoImagenService->eventoExiste($eventoId)) {
            return $this->respondError(
                'El evento solicitado no existe',
                ['evento_id' => 'No se encontro un evento con el id proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        if ($this->eventoImagenService->obtenerAdminPorId($eventoId, $imagenId) === null) {
            return $this->respondError(
                'La imagen solicitada no existe',
                ['imagen_id' => 'No se encontro una imagen para el evento proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        $data = $this->request->getJSON(true) ?? $this->request->getRawInput();

        if ($validationErrors = $this->validarActualizacion($data)) {
            return $this->respondError(
                'Error de validacion al actualizar la imagen',
                $validationErrors,
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $imagen = $this->eventoImagenService->actualizar($eventoId, $imagenId, $data);

        if ($imagen === null) {
            return $this->respondError(
                'No fue posible actualizar la imagen del evento',
                ['imagen' => 'La imagen no pudo ser actualizada correctamente.'],
                ResponseInterface::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->respondSuccess('Imagen de evento actualizada correctamente', $imagen);
    }

    public function delete($eventoId = null, $imagenId = null): ResponseInterface
    {
        $eventoId = (int) $eventoId;
        $imagenId = (int) $imagenId;

        if ($eventoId <= 0 || $imagenId <= 0) {
            return $this->respondError(
                'Los identificadores proporcionados son invalidos',
                [
                    'evento_id' => 'El id del evento debe ser un entero positivo.',
                    'imagen_id' => 'El id de la imagen debe ser un entero positivo.',
                ],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        if (! $this->eventoImagenService->eventoExiste($eventoId)) {
            return $this->respondError(
                'El evento solicitado no existe',
                ['evento_id' => 'No se encontro un evento con el id proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        if ($this->eventoImagenService->obtenerAdminPorId($eventoId, $imagenId) === null) {
            return $this->respondError(
                'La imagen solicitada no existe',
                ['imagen_id' => 'No se encontro una imagen para el evento proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        if (! $this->eventoImagenService->eliminar($eventoId, $imagenId)) {
            return $this->respondError(
                'No fue posible eliminar la imagen del evento',
                ['imagen' => 'La imagen no pudo ser eliminada correctamente.'],
                ResponseInterface::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->respondSuccess('Imagen de evento eliminada correctamente', null);
    }

    private function validarCarga(int $eventoId, array $data, $archivo): array
    {
        $rules = [
            'tipo'      => 'required|in_list[portada,banner,galeria,miniatura]',
            'principal' => 'permit_empty|in_list[0,1]',
            'orden'     => 'permit_empty|integer|greater_than_equal_to[0]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validator->getErrors();
        }

        $errors = [];

        if ($archivo === null || ! $archivo->isValid()) {
            $errors['imagen'] = 'El archivo de imagen es obligatorio y debe ser valido.';

            return $errors;
        }

        $mimeType = strtolower((string) $archivo->getMimeType());
        $extension = strtolower((string) ($archivo->getClientExtension() ?: $archivo->getExtension()));
        $mimePermitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/x-png', 'image/webp'];
        $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'webp'];

        if (! in_array($mimeType, $mimePermitidos, true) || ! in_array($extension, $extensionesPermitidas, true)) {
            $errors['imagen'] = 'La imagen debe estar en formato jpg, jpeg, png o webp.';
        }

        if ($archivo->getSizeByUnit('mb') > 2) {
            $errors['imagen'] = 'La imagen no puede superar los 2 MB.';
        }

        return $errors;
    }

    private function validarActualizacion(array $data): array
    {
        $rules = [
            'tipo'      => 'required|in_list[portada,banner,galeria,miniatura]',
            'principal' => 'permit_empty|in_list[0,1]',
            'orden'     => 'permit_empty|integer|greater_than_equal_to[0]',
            'estado'    => 'permit_empty|in_list[activo,inactivo]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validator->getErrors();
        }

        return [];
    }
}
