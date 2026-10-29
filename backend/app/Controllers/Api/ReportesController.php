<?php

namespace App\Controllers\Api;

use App\Services\ReportesService;
use CodeIgniter\HTTP\ResponseInterface;

class ReportesController extends BaseApiController
{
    public function __construct(
        private readonly ReportesService $reportesService = new ReportesService()
    ) {
    }

    public function index(): ResponseInterface
    {
        $filters = [
            'fecha_desde'  => $this->request->getGet('fecha_desde'),
            'fecha_hasta'  => $this->request->getGet('fecha_hasta'),
            'evento_id'    => $this->request->getGet('evento_id'),
            'categoria_id' => $this->request->getGet('categoria_id'),
            'estado_orden' => $this->request->getGet('estado_orden'),
        ];

        $rules = [
            'fecha_desde'  => 'permit_empty|valid_date[Y-m-d]',
            'fecha_hasta'  => 'permit_empty|valid_date[Y-m-d]',
            'evento_id'    => 'permit_empty|integer|greater_than[0]',
            'categoria_id' => 'permit_empty|integer|greater_than[0]',
            'estado_orden' => 'permit_empty|in_list[pagada,pendiente,fallida,cancelada]',
        ];

        if (! $this->validateData($filters, $rules)) {
            return $this->respondError(
                'Error de validacion al consultar reportes',
                $this->validator->getErrors(),
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (
            ! empty($filters['fecha_desde'])
            && ! empty($filters['fecha_hasta'])
            && (string) $filters['fecha_desde'] > (string) $filters['fecha_hasta']
        ) {
            return $this->respondError(
                'Error de validacion al consultar reportes',
                ['fecha_hasta' => 'La fecha hasta no puede ser menor que la fecha desde.'],
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return $this->respondSuccess(
            'Reportes obtenidos correctamente',
            $this->reportesService->obtenerReportes($filters)
        );
    }
}
