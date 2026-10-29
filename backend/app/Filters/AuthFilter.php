<?php

namespace App\Filters;

use App\Services\AuthService;
use App\Services\UsuarioService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authorizationHeader = $request->getHeaderLine('Authorization');

        if ($authorizationHeader === '' || ! preg_match('/^Bearer\s+(.+)$/i', $authorizationHeader, $matches)) {
            return service('response')
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)
                ->setJSON([
                    'success' => false,
                    'message' => 'Token requerido',
                    'data'    => null,
                    'errors'  => null,
                ]);
        }

        $token = trim($matches[1]);
        $payload = (new AuthService())->validarTokenProvisional($token);

        if ($payload === null) {
            return service('response')
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)
                ->setJSON([
                    'success' => false,
                    'message' => 'Token invalido o expirado',
                    'data'    => null,
                    'errors'  => null,
                ]);
        }

        $usuario = (new UsuarioService())->obtenerPorId((int) $payload['user_id']);

        if ($usuario === null) {
            return service('response')
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)
                ->setJSON([
                    'success' => false,
                    'message' => 'Token invalido o expirado',
                    'data'    => null,
                    'errors'  => null,
                ]);
        }

        set_authenticated_user($usuario);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
