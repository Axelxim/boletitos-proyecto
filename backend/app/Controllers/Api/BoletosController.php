<?php

namespace App\Controllers\Api;

use App\Services\BoletoService;
use App\Services\OrdenService;
use CodeIgniter\HTTP\ResponseInterface;

class BoletosController extends BaseApiController
{
    public function __construct(
        private readonly BoletoService $boletoService = new BoletoService(),
        private readonly OrdenService $ordenService = new OrdenService()
    ) {
    }

    public function index(): ResponseInterface
    {
        return $this->respondSuccess(
            'Boletos obtenidos correctamente',
            $this->boletoService->obtenerTodosAdmin()
        );
    }

    public function show($id = null): ResponseInterface
    {
        $id = (int) $id;

        if ($id <= 0) {
            return $this->respondError(
                'El identificador del boleto es invalido',
                ['id' => 'El id debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        $boleto = $this->boletoService->obtenerPorId($id);

        if ($boleto === null) {
            return $this->respondError(
                'El boleto solicitado no existe',
                ['id' => 'No se encontro un boleto con el id proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        return $this->respondSuccess('Boleto obtenido correctamente', $boleto);
    }

    public function porOrden($ordenId = null): ResponseInterface
    {
        $ordenId = (int) $ordenId;

        if ($ordenId <= 0) {
            return $this->respondError(
                'El identificador de la orden es invalido',
                ['orden_id' => 'El id de la orden debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        $orden = $this->ordenService->obtenerPorId($ordenId);

        if ($orden === null) {
            return $this->respondError(
                'La orden solicitada no existe',
                ['orden_id' => 'No se encontro una orden con el id proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        return $this->respondSuccess('Boletos obtenidos correctamente', [
            'orden'   => $orden,
            'boletos' => $this->boletoService->obtenerPorOrden($ordenId),
        ]);
    }
}
