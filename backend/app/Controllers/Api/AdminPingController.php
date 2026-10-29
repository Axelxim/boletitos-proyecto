<?php

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;

class AdminPingController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        return $this->respondSuccess(
            'Ruta administrativa protegida funcionando correctamente',
            [
                'area'   => 'admin',
                'status' => 'ok',
            ]
        );
    }
}
