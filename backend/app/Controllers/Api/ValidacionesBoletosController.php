<?php

namespace App\Controllers\Api;

use App\Services\ValidacionBoletoService;
use CodeIgniter\HTTP\ResponseInterface;

class ValidacionesBoletosController extends BaseApiController
{
    public function __construct(private readonly ValidacionBoletoService $validacionBoletoService = new ValidacionBoletoService())
    {
    }

    public function validar(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $rules = [
            'codigo_qr' => 'required|max_length[255]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->respondError(
                'Error de validacion del boleto',
                $this->validator->getErrors(),
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $result = $this->validacionBoletoService->validarPorCodigoQr((string) $data['codigo_qr']);

        if (! $result['success']) {
            return $this->respondError($result['message'], $result['data'], ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->respondSuccess($result['message'], $result['data']);
    }
}
