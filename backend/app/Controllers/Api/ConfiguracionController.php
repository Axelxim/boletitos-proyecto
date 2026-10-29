<?php

namespace App\Controllers\Api;

use App\Services\ConfiguracionService;
use CodeIgniter\HTTP\ResponseInterface;

class ConfiguracionController extends BaseApiController
{
    public function __construct(
        private readonly ConfiguracionService $configuracionService = new ConfiguracionService()
    ) {
    }

    public function index(): ResponseInterface
    {
        return $this->respondSuccess(
            'Configuracion obtenida correctamente',
            $this->configuracionService->obtenerConfiguracion()
        );
    }

    public function update(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $values = $data['values'] ?? $data;

        if (! is_array($values) || $values === []) {
            return $this->respondError(
                'No se recibieron valores para actualizar',
                ['values' => 'Debes enviar al menos una clave editable.'],
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $result = $this->configuracionService->actualizarConfiguracion($values);

        if (! ($result['success'] ?? false)) {
            return $this->respondError(
                'Error de validacion al actualizar configuracion',
                $result['errors'] ?? [],
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return $this->respondSuccess(
            'Configuracion actualizada correctamente',
            $result['data'] ?? []
        );
    }
}
