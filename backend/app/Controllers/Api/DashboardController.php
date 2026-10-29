<?php

namespace App\Controllers\Api;

use App\Services\DashboardService;
use CodeIgniter\HTTP\ResponseInterface;

class DashboardController extends BaseApiController
{
    public function __construct(private readonly DashboardService $dashboardService = new DashboardService())
    {
    }

    public function resumen(): ResponseInterface
    {
        return $this->respondSuccess(
            'Resumen del dashboard obtenido correctamente',
            $this->dashboardService->obtenerResumen()
        );
    }
}
