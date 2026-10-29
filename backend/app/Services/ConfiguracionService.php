<?php

namespace App\Services;

use App\Models\ConfiguracionModel;
use Config\Email;

class ConfiguracionService
{
    private const EDITABLE_KEYS = [
        'sistema_nombre',
        'sistema_nombre_corto',
        'sistema_descripcion',
        'correo_contacto',
        'telefono_contacto',
        'url_publica',
        'zona_horaria',
        'moneda',
        'logo_principal',
        'logo_oscuro',
        'favicon',
        'color_principal',
        'color_secundario',
        'permitir_compra_invitado',
        'comision_servicio',
        'impuesto_porcentaje',
        'max_boletos_orden',
        'minutos_expiracion_orden',
        'comprador_correo_obligatorio',
        'comprador_telefono_obligatorio',
        'paypal_modo',
        'prefijo_folio_orden',
        'prefijo_folio_boleto',
        'permitir_validacion_folio',
        'permitir_validacion_qr',
        'boleto_texto_legal',
        'boleto_instrucciones',
        'mostrar_eventos_finalizados',
        'home_destacados_limite',
        'eventos_por_pagina',
        'eventos_orden_default',
        'permitir_eventos_sin_imagen',
    ];

    public function __construct(
        private readonly ConfiguracionModel $configuracionModel = new ConfiguracionModel(),
        private readonly BitacoraService $bitacoraService = new BitacoraService()
    ) {
    }

    public function obtenerConfiguracion(): array
    {
        $rows = $this->configuracionModel->orderBy('id', 'ASC')->findAll();
        $configMap = [];

        foreach ($rows as $row) {
            $configMap[$row['clave']] = $this->castValue($row['valor'], $row['tipo'] ?? 'texto');
        }

        $sections = $this->buildSections($configMap);

        return [
            'editable_keys' => self::EDITABLE_KEYS,
            'sections'      => $sections,
        ];
    }

    public function actualizarConfiguracion(array $payload): array
    {
        $rows = $this->configuracionModel->findAll();
        $existingByKey = [];

        foreach ($rows as $row) {
            $existingByKey[$row['clave']] = $row;
        }

        $validated = $this->validatePayload($payload);

        if ($validated['errors'] !== []) {
            return [
                'success' => false,
                'errors'  => $validated['errors'],
            ];
        }

        $changes = [];
        $previousValues = [];

        foreach ($validated['values'] as $key => $value) {
            $meta = $this->getDefinitionByKey($key);
            $serializedValue = $this->serializeValue($value, $meta['type']);
            $currentRow = $existingByKey[$key] ?? null;
            $currentValue = $currentRow ? $this->castValue($currentRow['valor'], $currentRow['tipo'] ?? $meta['type']) : null;

            if ($currentRow !== null && $currentValue === $value) {
                continue;
            }

            $data = [
                'clave'       => $key,
                'valor'       => $serializedValue,
                'descripcion' => $meta['description'],
                'tipo'        => $meta['type'],
                'updated_at'  => app_timestamp(),
            ];

            if ($currentRow === null) {
                $data['created_at'] = app_timestamp();
                $this->configuracionModel->insert($data);
            } else {
                $this->configuracionModel->update((int) $currentRow['id'], $data);
                $previousValues[$key] = $currentValue;
            }

            $changes[$key] = $value;
        }

        if ($changes !== []) {
            $this->bitacoraService->registrar([
                'usuario_id'       => get_authenticated_user()['id'] ?? null,
                'modulo'           => 'configuracion',
                'accion'           => 'update',
                'descripcion'      => 'Configuracion general actualizada',
                'datos_anteriores' => $previousValues,
                'datos_nuevos'     => $changes,
            ]);
        }

        return [
            'success' => true,
            'data'    => $this->obtenerConfiguracion(),
        ];
    }

    private function validatePayload(array $payload): array
    {
        $errors = [];
        $values = [];

        foreach ($payload as $key => $value) {
            if (! in_array($key, self::EDITABLE_KEYS, true)) {
                $errors[$key] = 'La clave no esta permitida para su edicion.';
                continue;
            }

            $definition = $this->getDefinitionByKey($key);
            $normalized = $this->normalizeValue($value, $definition['type']);

            if ($normalized['error'] !== null) {
                $errors[$key] = $normalized['error'];
                continue;
            }

            $domainError = $this->validateDomainRules($key, $normalized['value']);

            if ($domainError !== null) {
                $errors[$key] = $domainError;
                continue;
            }

            $values[$key] = $normalized['value'];
        }

        return [
            'errors' => $errors,
            'values' => $values,
        ];
    }

    private function validateDomainRules(string $key, mixed $value): ?string
    {
        return match ($key) {
            'comision_servicio' => (float) $value < 0 ? 'La comision no puede ser negativa.' : null,
            'impuesto_porcentaje' => ((float) $value < 0 || (float) $value > 100) ? 'El impuesto debe estar entre 0 y 100.' : null,
            'max_boletos_orden', 'minutos_expiracion_orden', 'home_destacados_limite', 'eventos_por_pagina' => (int) $value <= 0 ? 'El valor debe ser mayor a 0.' : null,
            'moneda' => preg_match('/^[A-Z]{3,5}$/', (string) $value) !== 1 ? 'La moneda debe tener formato valido, por ejemplo MXN.' : null,
            'zona_horaria' => ! in_array((string) $value, timezone_identifiers_list(), true) ? 'La zona horaria no es valida.' : null,
            'url_publica', 'logo_principal', 'logo_oscuro', 'favicon' => ($value !== '' && filter_var((string) $value, FILTER_VALIDATE_URL) === false && ! preg_match('#^/?[A-Za-z0-9/_\.-]+$#', (string) $value)) ? 'La URL o ruta no es valida.' : null,
            'paypal_modo' => ! in_array((string) $value, ['sandbox', 'live'], true) ? 'El modo de PayPal debe ser sandbox o live.' : null,
            'eventos_orden_default' => ! in_array((string) $value, ['recientes', 'fecha_asc', 'fecha_desc', 'destacados'], true) ? 'El criterio de orden no es valido.' : null,
            default => null,
        };
    }

    private function buildSections(array $configMap): array
    {
        $email = config(Email::class);
        $paypalStatus = $this->resolvePaypalStatus();

        return [
            'general' => [
                'title'  => 'General',
                'fields' => $this->buildFieldGroup($configMap, [
                    'sistema_nombre',
                    'sistema_nombre_corto',
                    'sistema_descripcion',
                    'correo_contacto',
                    'telefono_contacto',
                    'url_publica',
                    'zona_horaria',
                    'moneda',
                ]),
            ],
            'apariencia' => [
                'title'  => 'Apariencia',
                'fields' => $this->buildFieldGroup($configMap, [
                    'logo_principal',
                    'logo_oscuro',
                    'favicon',
                    'color_principal',
                    'color_secundario',
                ]),
            ],
            'compras' => [
                'title'  => 'Compras',
                'fields' => $this->buildFieldGroup($configMap, [
                    'permitir_compra_invitado',
                    'comision_servicio',
                    'impuesto_porcentaje',
                    'max_boletos_orden',
                    'minutos_expiracion_orden',
                    'comprador_correo_obligatorio',
                    'comprador_telefono_obligatorio',
                ]),
            ],
            'pagos' => [
                'title'  => 'Pagos',
                'fields' => $this->buildFieldGroup($configMap, ['paypal_modo']),
                'info'   => [
                    'paypal_estado' => $paypalStatus['status'],
                    'paypal_message' => $paypalStatus['message'],
                ],
            ],
            'correo' => [
                'title'  => 'Correo',
                'fields' => [],
                'info'   => [
                    'from_email' => $email->fromEmail,
                    'from_name'  => $email->fromName,
                    'protocol'   => $email->protocol,
                    'smtp_host'  => $email->SMTPHost,
                    'smtp_port'  => $email->SMTPPort,
                    'smtp_crypto'=> $email->SMTPCrypto,
                    'status'     => ($email->fromEmail !== '' && $email->SMTPHost !== '' && $email->SMTPUser !== '' && $email->SMTPPass !== '') ? 'Configurado' : 'No configurado',
                ],
            ],
            'boletos' => [
                'title'  => 'Boletos',
                'fields' => $this->buildFieldGroup($configMap, [
                    'prefijo_folio_orden',
                    'prefijo_folio_boleto',
                    'permitir_validacion_folio',
                    'permitir_validacion_qr',
                    'boleto_texto_legal',
                    'boleto_instrucciones',
                ]),
            ],
            'eventos' => [
                'title'  => 'Eventos',
                'fields' => $this->buildFieldGroup($configMap, [
                    'mostrar_eventos_finalizados',
                    'home_destacados_limite',
                    'eventos_por_pagina',
                    'eventos_orden_default',
                    'permitir_eventos_sin_imagen',
                ]),
            ],
        ];
    }

    private function buildFieldGroup(array $configMap, array $keys): array
    {
        $fields = [];

        foreach ($keys as $key) {
            $definition = $this->getDefinitionByKey($key);
            $fields[] = [
                'key'         => $key,
                'label'       => $definition['label'],
                'type'        => $definition['type'],
                'editable'    => true,
                'value'       => $configMap[$key] ?? $definition['default'],
                'description' => $definition['description'],
                'options'     => $definition['options'] ?? null,
            ];
        }

        return $fields;
    }

    private function getDefinitionByKey(string $key): array
    {
        $definitions = [
            'sistema_nombre' => ['label' => 'Nombre del sistema', 'type' => 'text', 'default' => 'Boletitos', 'description' => 'Nombre publico principal.'],
            'sistema_nombre_corto' => ['label' => 'Nombre corto', 'type' => 'text', 'default' => 'Boletitos', 'description' => 'Nombre corto de referencia.'],
            'sistema_descripcion' => ['label' => 'Descripcion', 'type' => 'textarea', 'default' => '', 'description' => 'Descripcion institucional.'],
            'correo_contacto' => ['label' => 'Correo de contacto', 'type' => 'email', 'default' => '', 'description' => 'Correo publico de contacto.'],
            'telefono_contacto' => ['label' => 'Telefono', 'type' => 'text', 'default' => '', 'description' => 'Telefono publico de contacto.'],
            'url_publica' => ['label' => 'URL publica', 'type' => 'url', 'default' => '', 'description' => 'Dominio o ruta publica del sistema.'],
            'zona_horaria' => ['label' => 'Zona horaria', 'type' => 'text', 'default' => 'America/Mexico_City', 'description' => 'Zona horaria operativa.'],
            'moneda' => ['label' => 'Moneda principal', 'type' => 'text', 'default' => 'MXN', 'description' => 'Moneda por defecto.'],
            'logo_principal' => ['label' => 'Logo principal', 'type' => 'url', 'default' => '', 'description' => 'Ruta o URL del logo principal.'],
            'logo_oscuro' => ['label' => 'Logo oscuro', 'type' => 'url', 'default' => '', 'description' => 'Ruta o URL del logo para fondos oscuros.'],
            'favicon' => ['label' => 'Favicon', 'type' => 'url', 'default' => '', 'description' => 'Ruta o URL del favicon.'],
            'color_principal' => ['label' => 'Color principal', 'type' => 'text', 'default' => '#C9A227', 'description' => 'Color base de marca.'],
            'color_secundario' => ['label' => 'Color secundario', 'type' => 'text', 'default' => '#050505', 'description' => 'Color secundario de marca.'],
            'permitir_compra_invitado' => ['label' => 'Permitir compra como invitado', 'type' => 'boolean', 'default' => true, 'description' => 'Habilita compras sin registro.'],
            'comision_servicio' => ['label' => 'Comision de servicio', 'type' => 'number', 'default' => 0, 'description' => 'Monto fijo o base de servicio.'],
            'impuesto_porcentaje' => ['label' => 'Impuesto (%)', 'type' => 'number', 'default' => 0, 'description' => 'Porcentaje de impuesto aplicado.'],
            'max_boletos_orden' => ['label' => 'Maximo de boletos por orden', 'type' => 'integer', 'default' => 10, 'description' => 'Limite por compra.'],
            'minutos_expiracion_orden' => ['label' => 'Vigencia orden pendiente (min)', 'type' => 'integer', 'default' => 30, 'description' => 'Minutos antes de expirar una orden pendiente.'],
            'comprador_correo_obligatorio' => ['label' => 'Correo obligatorio', 'type' => 'boolean', 'default' => true, 'description' => 'Requiere correo al comprador.'],
            'comprador_telefono_obligatorio' => ['label' => 'Telefono obligatorio', 'type' => 'boolean', 'default' => true, 'description' => 'Requiere telefono al comprador.'],
            'paypal_modo' => ['label' => 'Modo PayPal', 'type' => 'select', 'default' => 'sandbox', 'description' => 'Modo operativo de PayPal.', 'options' => ['sandbox', 'live']],
            'prefijo_folio_orden' => ['label' => 'Prefijo de orden', 'type' => 'text', 'default' => 'ORD', 'description' => 'Prefijo para folios de orden.'],
            'prefijo_folio_boleto' => ['label' => 'Prefijo de boleto', 'type' => 'text', 'default' => 'BOL', 'description' => 'Prefijo para folios de boleto.'],
            'permitir_validacion_folio' => ['label' => 'Permitir validacion por folio', 'type' => 'boolean', 'default' => true, 'description' => 'Permite validar por folio manual.'],
            'permitir_validacion_qr' => ['label' => 'Permitir validacion por QR', 'type' => 'boolean', 'default' => true, 'description' => 'Permite validar por QR.'],
            'boleto_texto_legal' => ['label' => 'Texto legal del boleto', 'type' => 'textarea', 'default' => '', 'description' => 'Texto legal mostrado al asistente.'],
            'boleto_instrucciones' => ['label' => 'Instrucciones para el asistente', 'type' => 'textarea', 'default' => '', 'description' => 'Instrucciones previas al evento.'],
            'mostrar_eventos_finalizados' => ['label' => 'Mostrar eventos finalizados', 'type' => 'boolean', 'default' => false, 'description' => 'Muestra eventos pasados en catalogo.'],
            'home_destacados_limite' => ['label' => 'Cantidad de destacados en Home', 'type' => 'integer', 'default' => 5, 'description' => 'Numero maximo de destacados.'],
            'eventos_por_pagina' => ['label' => 'Eventos por pagina', 'type' => 'integer', 'default' => 12, 'description' => 'Tamano de paginacion publica.'],
            'eventos_orden_default' => ['label' => 'Criterio de orden', 'type' => 'select', 'default' => 'recientes', 'description' => 'Orden por defecto en listados.', 'options' => ['recientes', 'fecha_asc', 'fecha_desc', 'destacados']],
            'permitir_eventos_sin_imagen' => ['label' => 'Permitir eventos sin imagen', 'type' => 'boolean', 'default' => true, 'description' => 'Habilita publicar eventos sin portada.'],
        ];

        return $definitions[$key];
    }

    private function normalizeValue(mixed $value, string $type): array
    {
        return match ($type) {
            'boolean' => ['value' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false, 'error' => null],
            'integer' => is_numeric($value) ? ['value' => (int) $value, 'error' => null] : ['value' => null, 'error' => 'El valor debe ser entero.'],
            'number' => is_numeric($value) ? ['value' => round((float) $value, 2), 'error' => null] : ['value' => null, 'error' => 'El valor debe ser numerico.'],
            default => ['value' => trim((string) $value), 'error' => null],
        };
    }

    private function serializeValue(mixed $value, string $type): string
    {
        return match ($type) {
            'boolean' => $value ? 'true' : 'false',
            'integer' => (string) ((int) $value),
            'number' => number_format((float) $value, 2, '.', ''),
            default => (string) $value,
        };
    }

    private function castValue(?string $value, string $type): mixed
    {
        return match ($type) {
            'boolean', 'booleano' => in_array(strtolower((string) $value), ['1', 'true', 'si', 'yes'], true),
            'integer' => (int) $value,
            'number', 'numero' => (float) $value,
            default => (string) $value,
        };
    }

    private function resolvePaypalStatus(): array
    {
        $candidateKeys = [
            env('PAYPAL_CLIENT_ID'),
            env('PAYPAL_CLIENT_SECRET'),
            env('PAYPAL_MODE'),
            env('PAYPAL_API_URL'),
            env('paypal.clientId'),
            env('paypal.clientSecret'),
            env('paypal.sandboxClientId'),
            env('paypal.sandboxSecret'),
            env('paypal.liveClientId'),
            env('paypal.liveSecret'),
        ];

        $hasCredentials = count(array_filter($candidateKeys, static fn (mixed $value): bool => is_string($value) && trim($value) !== '')) >= 2;

        return [
            'status'  => $hasCredentials ? 'Configurado' : 'No configurado',
            'message' => $hasCredentials
                ? 'Se detectaron credenciales de PayPal en variables de entorno.'
                : 'No se detectaron credenciales publicas y privadas de PayPal en variables de entorno.',
        ];
    }
}
