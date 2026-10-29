<?php

namespace App\Controllers\Api;

use App\Services\AuthService;
use App\Services\OrdenDetalleService;
use App\Services\OrdenService;
use App\Services\PagoService;
use App\Services\BoletoService;
use App\Services\BoletoCorreoService;
use App\Services\UsuarioService;
use CodeIgniter\HTTP\ResponseInterface;

class OrdenesController extends BaseApiController
{
    public function __construct(
        private readonly OrdenService $ordenService = new OrdenService(),
        private readonly OrdenDetalleService $ordenDetalleService = new OrdenDetalleService(),
        private readonly PagoService $pagoService = new PagoService(),
        private readonly BoletoService $boletoService = new BoletoService(),
        private readonly BoletoCorreoService $boletoCorreoService = new BoletoCorreoService()
    ) {
    }

    public function index(): ResponseInterface
    {
        return $this->respondSuccess(
            'Ordenes obtenidas correctamente',
            $this->ordenService->obtenerTodasAdminEnriquecidas()
        );
    }

    public function show($id = null): ResponseInterface
    {
        $id = (int) $id;

        if ($id <= 0) {
            return $this->respondError(
                'El identificador de la orden es invalido',
                ['id' => 'El id debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        $orden = $this->ordenService->obtenerPorIdAdminEnriquecida($id);

        if ($orden === null) {
            return $this->respondError(
                'La orden solicitada no existe',
                ['id' => 'No se encontro una orden con el id proporcionado.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        return $this->respondSuccess('Orden obtenida correctamente', $orden);
    }

    public function store(): ResponseInterface
    {
        $this->resolveOptionalAuthenticatedUser();
        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $authenticatedUser = get_authenticated_user();

        $rules = [
            'nombre_comprador'   => 'required|min_length[2]|max_length[255]',
            'telefono_comprador' => 'required|min_length[7]|max_length[30]',
            'subtotal'           => 'required|decimal',
            'comision'           => 'required|decimal',
            'total'              => 'required|decimal',
        ];

        if ($authenticatedUser === null) {
            $rules['correo_comprador'] = 'required|valid_email|max_length[255]';
        }

        if (! $this->validateData($data, $rules)) {
            return $this->respondError(
                'Error de validacion al crear la orden',
                $this->validator->getErrors(),
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $financialErrors = $this->validateFinancials($data);

        if ($financialErrors !== []) {
            return $this->respondError(
                'Error de validacion al crear la orden',
                $financialErrors,
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $orden = $this->ordenService->crear($data);

        if ($orden === null) {
            return $this->respondError(
                'No fue posible crear la orden',
                ['orden' => 'La orden no pudo ser creada.'],
                ResponseInterface::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->response
            ->setStatusCode(ResponseInterface::HTTP_CREATED)
            ->setJSON([
                'success' => true,
                'message' => 'Orden creada correctamente',
                'data'    => $orden,
                'errors'  => null,
            ]);
    }

    public function consultarMisBoletos(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $rules = [
            'folio' => 'required|max_length[255]',
            'correo_comprador' => 'required|valid_email|max_length[255]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->respondError(
                'Error de validacion al consultar tus boletos',
                $this->validator->getErrors(),
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $orden = $this->ordenService->obtenerPorFolioYCorreo(
            (string) ($data['folio'] ?? ''),
            (string) ($data['correo_comprador'] ?? '')
        );

        if ($orden === null) {
            return $this->respondError(
                'No encontramos boletos para los datos proporcionados',
                ['folio' => 'Verifica el folio y el correo comprador.'],
                ResponseInterface::HTTP_NOT_FOUND
            );
        }

        return $this->respondSuccess('Boletos encontrados correctamente', $orden);
    }

    public function misBoletosAutenticado(): ResponseInterface
    {
        $usuario = get_authenticated_user();

        if ($usuario === null || ! isset($usuario['id'])) {
            return $this->respondError(
                'No fue posible obtener el usuario autenticado',
                ['usuario' => 'No existe una sesion valida.'],
                ResponseInterface::HTTP_UNAUTHORIZED
            );
        }

        $ordenes = $this->ordenService->obtenerPorUsuarioId((int) $usuario['id']);

        return $this->respondSuccess('Boletos obtenidos correctamente', $ordenes);
    }

    public function storeDetalle($ordenId = null): ResponseInterface
    {
        $ordenId = (int) $ordenId;

        if ($ordenId <= 0) {
            return $this->respondError(
                'El identificador de la orden es invalido',
                ['orden_id' => 'El id de la orden debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $rules = [
            'tipo_boleto_id' => 'required|integer|greater_than[0]',
            'cantidad'       => 'required|integer|greater_than[0]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->respondError(
                'Error de validacion al agregar el detalle de la orden',
                $this->validator->getErrors(),
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $result = $this->ordenDetalleService->agregarDetalle($ordenId, $data);

        if (! $result['success']) {
            return $this->respondError(
                'Error de validacion al agregar el detalle de la orden',
                $result['errors'],
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return $this->response
            ->setStatusCode(ResponseInterface::HTTP_CREATED)
            ->setJSON([
                'success' => true,
                'message' => 'Detalle de orden agregado correctamente',
                'data'    => $result['data'],
                'errors'  => null,
            ]);
    }

    public function storePagoSimulado($ordenId = null): ResponseInterface
    {
        $ordenId = (int) $ordenId;

        if ($ordenId <= 0) {
            return $this->respondError(
                'El identificador de la orden es invalido',
                ['orden_id' => 'El id de la orden debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $rules = [
            'estado'          => 'required|in_list[aprobado,rechazado,cancelado]',
            'referencia_pago' => 'permit_empty|max_length[255]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->respondError(
                'Error de validacion al registrar el pago simulado',
                $this->validator->getErrors(),
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $result = $this->pagoService->registrarPagoSimulado($ordenId, $data);

        if (! $result['success']) {
            return $this->respondError(
                'Error de validacion al registrar el pago simulado',
                $result['errors'],
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return $this->respondSuccess('Pago simulado registrado correctamente', $result['data']);
    }

    public function generarBoletos($ordenId = null): ResponseInterface
    {
        $ordenId = (int) $ordenId;

        if ($ordenId <= 0) {
            return $this->respondError(
                'El identificador de la orden es invalido',
                ['orden_id' => 'El id de la orden debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        $result = $this->boletoService->generarPorOrden($ordenId);

        if (! $result['success']) {
            return $this->respondError(
                'Error al generar los boletos',
                $result['errors'],
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return $this->respondSuccess('Boletos generados correctamente', $result['data']);
    }

    public function reenviarCorreo($ordenId = null): ResponseInterface
    {
        $ordenId = (int) $ordenId;

        if ($ordenId <= 0) {
            return $this->respondError(
                'El identificador de la orden es invalido',
                ['orden_id' => 'El id de la orden debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        $result = $this->boletoCorreoService->enviarCompraConfirmada($ordenId, true);

        if (! ($result['success'] ?? false)) {
            return $this->respondError(
                'No fue posible reenviar el correo',
                ['correo' => $result['message'] ?? 'Ocurrio un error al reenviar el correo.'],
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return $this->respondSuccess('Correo reenviado correctamente', [
            'orden_id' => $ordenId,
            'message'  => $result['message'] ?? 'Correo reenviado correctamente.',
        ]);
    }

    public function crearOrdenPaypal($ordenId = null): ResponseInterface
    {
        $ordenId = (int) $ordenId;

        if ($ordenId <= 0) {
            return $this->respondError(
                'El identificador de la orden es invalido',
                ['orden_id' => 'El id de la orden debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        $result = $this->pagoService->crearOrdenPaypal($ordenId);

        if (! ($result['success'] ?? false)) {
            return $this->respondError(
                'No fue posible crear la orden PayPal',
                $result['errors'] ?? ['paypal' => 'Ocurrio un error al crear la orden PayPal.'],
                $result['status'] ?? ResponseInterface::HTTP_BAD_GATEWAY
            );
        }

        return $this->response
            ->setStatusCode($result['status'] ?? ResponseInterface::HTTP_CREATED)
            ->setJSON([
                'success' => true,
                'message' => 'Orden PayPal creada correctamente',
                'data'    => $result['data'],
                'errors'  => null,
            ]);
    }

    public function capturarOrdenPaypal($ordenId = null): ResponseInterface
    {
        $ordenId = (int) $ordenId;

        if ($ordenId <= 0) {
            return $this->respondError(
                'El identificador de la orden es invalido',
                ['orden_id' => 'El id de la orden debe ser un entero positivo.'],
                ResponseInterface::HTTP_BAD_REQUEST
            );
        }

        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $paypalOrderId = sanitize_simple_string((string) ($data['paypal_order_id'] ?? ''));

        if ($paypalOrderId === '') {
            return $this->respondError(
                'La referencia de PayPal es obligatoria',
                ['paypal_order_id' => 'Debes proporcionar el PayPal order ID.'],
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $result = $this->pagoService->capturarOrdenPaypal($ordenId, $paypalOrderId);

        if (! ($result['success'] ?? false)) {
            return $this->respondError(
                'No fue posible capturar la orden PayPal',
                $result['errors'] ?? ['paypal' => 'Ocurrio un error al capturar la orden PayPal.'],
                $result['status'] ?? ResponseInterface::HTTP_BAD_GATEWAY
            );
        }

        return $this->respondSuccess('Pago PayPal capturado correctamente', $result['data']);
    }

    private function validateFinancials(array $data): array
    {
        $errors = [];
        $subtotal = (float) ($data['subtotal'] ?? 0);
        $comision = (float) ($data['comision'] ?? 0);
        $total = (float) ($data['total'] ?? 0);

        if ($subtotal < 0) {
            $errors['subtotal'] = 'El subtotal no puede ser negativo.';
        }

        if ($comision < 0) {
            $errors['comision'] = 'La comision no puede ser negativa.';
        }

        if ($total < 0) {
            $errors['total'] = 'El total no puede ser negativo.';
        }

        if (round($subtotal + $comision, 2) !== round($total, 2)) {
            $errors['total'] = 'El total debe ser igual a subtotal + comision.';
        }

        return $errors;
    }

    private function resolveOptionalAuthenticatedUser(): void
    {
        if (get_authenticated_user() !== null) {
            return;
        }

        $authorizationHeader = $this->request->getHeaderLine('Authorization');

        if ($authorizationHeader === '' || ! preg_match('/^Bearer\s+(.+)$/i', $authorizationHeader, $matches)) {
            return;
        }

        $token = trim($matches[1]);
        $payload = (new AuthService())->validarTokenProvisional($token);

        if ($payload === null) {
            return;
        }

        $usuario = (new UsuarioService())->obtenerPorId((int) $payload['user_id']);

        if ($usuario === null) {
            return;
        }

        set_authenticated_user($usuario);
    }
}
