<?php

namespace App\Controllers\Api;

use App\Services\AuthService;
use CodeIgniter\HTTP\ResponseInterface;

class AuthController extends BaseApiController
{
    public function __construct(private readonly AuthService $authService = new AuthService())
    {
    }

    public function register(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        $rules = [
            'nombre'   => 'required|min_length[2]|max_length[255]',
            'correo'   => 'required|valid_email|max_length[255]',
            'password' => 'required|min_length[8]|max_length[255]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->respondError(
                'Error de validacion en el registro',
                $this->validator->getErrors(),
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $result = $this->authService->registrar($data);

        if (! $result['success']) {
            return $this->respondError($result['message'], $result['errors'], $result['status']);
        }

        return $this->response
            ->setStatusCode($result['status'])
            ->setJSON([
                'success' => true,
                'message' => $result['message'],
                'data'    => $result['data'],
                'errors'  => null,
            ]);
    }

    public function login(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        $rules = [
            'correo'   => 'required|valid_email|max_length[255]',
            'password' => 'required|min_length[8]|max_length[255]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->respondError(
                'Error de validacion en el inicio de sesion',
                $this->validator->getErrors(),
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $result = $this->authService->login((string) $data['correo'], (string) $data['password']);

        if (! $result['success']) {
            return $this->respondError($result['message'], $result['errors'], $result['status']);
        }

        return $this->respondSuccess($result['message'], $result['data']);
    }

    public function verificarCorreo(): ResponseInterface
    {
        $token = (string) $this->request->getGet('token');

        if ($token === '') {
            return $this->respondError(
                'El token de verificacion es obligatorio',
                ['token' => 'Debes proporcionar un token de verificacion.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        $result = $this->authService->verificarCorreo($token);

        if (! $result['success']) {
            return $this->respondError($result['message'], $result['errors'], $result['status']);
        }

        return $this->respondSuccess($result['message'], $result['data']);
    }

    public function reenviarVerificacion(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $rules = [
            'correo' => 'required|valid_email|max_length[255]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->respondError(
                'Error de validacion al reenviar verificacion',
                $this->validator->getErrors(),
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $result = $this->authService->reenviarVerificacion((string) $data['correo']);

        return $this->response
            ->setStatusCode($result['status'])
            ->setJSON([
                'success' => $result['success'],
                'message' => $result['message'],
                'data'    => [],
                'errors'  => null,
            ]);
    }
}
