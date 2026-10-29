<?php

namespace App\Controllers\Api;

use App\Services\PagoService;

class PayPalController extends BaseApiController
{
    public function __construct(private readonly PagoService $pagoService = new PagoService())
    {
    }

    public function config()
    {
        return $this->respondSuccess(
            'Configuracion publica de PayPal obtenida correctamente',
            $this->pagoService->obtenerPaypalConfiguracionPublica()
        );
    }
}
