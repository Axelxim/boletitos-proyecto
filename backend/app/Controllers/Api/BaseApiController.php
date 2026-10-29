<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

abstract class BaseApiController extends BaseController
{
    protected function respondSuccess(string $message, mixed $data = []): ResponseInterface
    {
        return $this->response->setJSON([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'errors'  => null,
        ]);
    }

    protected function respondError(string $message, array $errors = [], int $statusCode = ResponseInterface::HTTP_BAD_REQUEST): ResponseInterface
    {
        return $this->response
            ->setStatusCode($statusCode)
            ->setJSON([
                'success' => false,
                'message' => $message,
                'data'    => null,
                'errors'  => $errors,
            ]);
    }
}
