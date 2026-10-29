<?php

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;

class OptionsController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        return $this->response->setStatusCode(ResponseInterface::HTTP_NO_CONTENT);
    }
}
