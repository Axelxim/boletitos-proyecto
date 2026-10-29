<?php

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;

class PingController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        return $this->respondSuccess(
            'API Boletitos funcionando correctamente',
            [
                'app'    => 'Boletitos',
                'status' => 'ok',
            ]
        );
    }
}
