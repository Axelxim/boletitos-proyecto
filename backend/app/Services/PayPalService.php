<?php

namespace App\Services;

use RuntimeException;
use Throwable;

class PayPalService
{
    public function obtenerConfiguracionPublica(string $moneda = 'MXN'): array
    {
        return [
            'client_id' => $this->obtenerClientId(),
            'mode'      => $this->obtenerMode(),
            'currency'  => strtoupper(trim($moneda)) ?: 'MXN',
            'enabled'   => $this->estaConfigurado(),
        ];
    }

    public function estaConfigurado(): bool
    {
        return $this->obtenerClientId() !== '' && $this->obtenerClientSecret() !== '' && $this->obtenerApiUrl() !== '';
    }

    public function crearOrden(array $orden, string $moneda): array
    {
        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) ($orden['folio'] ?? ('ORDEN-' . ($orden['id'] ?? ''))),
                'custom_id'    => (string) ($orden['id'] ?? ''),
                'description'  => 'Compra de boletos en Boletitos',
                'amount'       => [
                    'currency_code' => strtoupper($moneda),
                    'value'         => number_format((float) ($orden['total'] ?? 0), 2, '.', ''),
                ],
            ]],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                        'user_action'               => 'PAY_NOW',
                        'brand_name'                => 'Boletitos',
                        'shipping_preference'       => 'NO_SHIPPING',
                    ],
                ],
            ],
        ];

        return $this->request('POST', '/v2/checkout/orders', $payload);
    }

    public function capturarOrden(string $paypalOrderId): array
    {
        return $this->request('POST', '/v2/checkout/orders/' . rawurlencode($paypalOrderId) . '/capture');
    }

    public function obtenerOrden(string $paypalOrderId): array
    {
        return $this->request('GET', '/v2/checkout/orders/' . rawurlencode($paypalOrderId));
    }

    public function sanitizarRespuesta(array $response): array
    {
        $purchaseUnit = $response['purchase_units'][0] ?? [];
        $capture = $purchaseUnit['payments']['captures'][0] ?? [];

        return [
            'id'             => $response['id'] ?? null,
            'intent'         => $response['intent'] ?? null,
            'status'         => $response['status'] ?? null,
            'reference_id'   => $purchaseUnit['reference_id'] ?? null,
            'custom_id'      => $purchaseUnit['custom_id'] ?? null,
            'amount'         => $purchaseUnit['amount'] ?? null,
            'payer'          => [
                'payer_id' => $response['payer']['payer_id'] ?? null,
                'email'    => $response['payer']['email_address'] ?? null,
            ],
            'capture'        => $capture === [] ? null : [
                'id'            => $capture['id'] ?? null,
                'status'        => $capture['status'] ?? null,
                'amount'        => $capture['amount'] ?? null,
                'final_capture' => $capture['final_capture'] ?? null,
            ],
            'links'          => array_map(
                static fn (array $link): array => [
                    'href'   => $link['href'] ?? null,
                    'rel'    => $link['rel'] ?? null,
                    'method' => $link['method'] ?? null,
                ],
                array_values(array_filter($response['links'] ?? [], 'is_array'))
            ),
        ];
    }

    private function request(string $method, string $path, ?array $json = null): array
    {
        if (! $this->estaConfigurado()) {
            throw new RuntimeException('PayPal no esta configurado correctamente en el entorno.');
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->obtenerAccessToken(),
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];

        try {
            [$statusCode, $rawBody] = $this->executeCurlRequest(
                $method,
                rtrim($this->obtenerApiUrl(), '/') . $path,
                $headers,
                $json === null ? null : json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        } catch (Throwable $exception) {
            throw new RuntimeException('No fue posible comunicarse con PayPal.', 0, $exception);
        }

        $body = json_decode($rawBody, true);

        if ($statusCode >= 400) {
            throw new RuntimeException($this->buildPaypalErrorMessage($body, $rawBody, 'PayPal devolvio un error al procesar la solicitud.'));
        }

        return is_array($body) ? $body : [];
    }

    private function obtenerAccessToken(): string
    {
        $headers = [
            'Authorization' => 'Basic ' . base64_encode($this->obtenerClientId() . ':' . $this->obtenerClientSecret()),
            'Content-Type'  => 'application/x-www-form-urlencoded',
            'Accept'        => 'application/json',
        ];

        try {
            [$statusCode, $rawBody] = $this->executeCurlRequest(
                'POST',
                rtrim($this->obtenerApiUrl(), '/') . '/v1/oauth2/token',
                $headers,
                'grant_type=client_credentials'
            );
        } catch (Throwable $exception) {
            throw new RuntimeException('No fue posible obtener el token OAuth de PayPal: ' . $exception->getMessage(), 0, $exception);
        }

        $body = json_decode($rawBody, true);

        if ($statusCode >= 400 || ! is_array($body) || empty($body['access_token'])) {
            throw new RuntimeException($this->buildPaypalErrorMessage($body, $rawBody, 'PayPal no devolvio un access token valido.'));
        }

        return (string) $body['access_token'];
    }

    private function executeCurlRequest(string $method, string $url, array $headers, ?string $body = null): array
    {
        $curl = curl_init($url);

        if ($curl === false) {
            throw new RuntimeException('No fue posible inicializar cURL para PayPal.');
        }

        $formattedHeaders = [];
        foreach ($headers as $name => $value) {
            $formattedHeaders[] = $name . ': ' . $value;
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $formattedHeaders,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => false,
        ];

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        if (! $this->debeVerificarSsl()) {
            $options[CURLOPT_SSL_VERIFYPEER] = false;
            $options[CURLOPT_SSL_VERIFYHOST] = 0;
        } else {
            $caInfo = $this->obtenerCaInfo();

            if ($caInfo !== '') {
                $options[CURLOPT_CAINFO] = $caInfo;
            }
        }

        curl_setopt_array($curl, $options);

        $rawBody = curl_exec($curl);

        if ($rawBody === false) {
            $error = curl_error($curl);
            $code = curl_errno($curl);
            curl_close($curl);

            throw new RuntimeException(trim($code . ' : ' . $error));
        }

        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return [$statusCode, (string) $rawBody];
    }

    private function buildPaypalErrorMessage(mixed $body, string $rawBody, string $defaultMessage): string
    {
        if (is_array($body)) {
            $parts = array_filter([
                $body['error_description'] ?? null,
                $body['message'] ?? null,
                $body['error'] ?? null,
                $body['name'] ?? null,
            ], static fn ($value): bool => is_string($value) && trim($value) !== '');

            if ($parts !== []) {
                return implode(' | ', array_unique(array_map('trim', $parts)));
            }
        }

        $rawBody = trim($rawBody);

        if ($rawBody !== '') {
            return $defaultMessage . ' Detalle: ' . mb_substr($rawBody, 0, 300);
        }

        return $defaultMessage;
    }

    private function obtenerClientId(): string
    {
        return trim((string) (env('PAYPAL_CLIENT_ID') ?: env('paypal.clientId') ?: env('paypal.sandboxClientId')));
    }

    private function obtenerClientSecret(): string
    {
        return trim((string) (env('PAYPAL_CLIENT_SECRET') ?: env('paypal.clientSecret') ?: env('paypal.sandboxSecret')));
    }

    private function obtenerMode(): string
    {
        return trim((string) (env('PAYPAL_MODE') ?: env('paypal.mode') ?: 'sandbox'));
    }

    private function obtenerApiUrl(): string
    {
        $configured = trim((string) (env('PAYPAL_API_URL') ?: env('paypal.apiUrl')));

        if ($configured !== '') {
            return $configured;
        }

        return $this->obtenerMode() === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function debeVerificarSsl(): bool
    {
        $value = strtolower(trim((string) env('PAYPAL_SSL_VERIFY')));

        if ($value === '') {
            return true;
        }

        return ! in_array($value, ['0', 'false', 'off', 'no'], true);
    }

    private function obtenerCaInfo(): string
    {
        return trim((string) (env('PAYPAL_CAINFO') ?: env('curl.cainfo') ?: env('openssl.cafile')));
    }

}
