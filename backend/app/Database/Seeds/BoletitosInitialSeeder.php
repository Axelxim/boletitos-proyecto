<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class BoletitosInitialSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        $this->insertIfMissing('roles', 'nombre', 'admin', [
            'nombre' => 'admin',
            'descripcion' => 'Administrador del sistema',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->insertIfMissing('roles', 'nombre', 'usuario', [
            'nombre' => 'usuario',
            'descripcion' => 'Comprador registrado',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($this->defaultConfiguration() as $key => [$value, $type, $description]) {
            $this->insertIfMissing('configuracion', 'clave', $key, [
                'clave' => $key,
                'valor' => $value,
                'tipo' => $type,
                'descripcion' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function insertIfMissing(string $table, string $column, string $value, array $data): void
    {
        if (! $this->db->table($table)->where($column, $value)->get()->getRowArray()) {
            $this->db->table($table)->insert($data);
        }
    }

    private function defaultConfiguration(): array
    {
        return [
            'sistema_nombre' => ['Boletitos', 'text', 'Nombre publico principal.'],
            'sistema_nombre_corto' => ['Boletitos', 'text', 'Nombre corto de referencia.'],
            'sistema_descripcion' => ['Compra boletos para tus eventos favoritos', 'textarea', 'Descripcion institucional.'],
            'correo_contacto' => ['', 'email', 'Correo publico de contacto.'],
            'telefono_contacto' => ['', 'text', 'Telefono publico de contacto.'],
            'url_publica' => ['https://mediumblue-worm-371886.hostingersite.com', 'url', 'Dominio publico del sistema.'],
            'zona_horaria' => ['America/Mexico_City', 'text', 'Zona horaria operativa.'],
            'moneda' => ['MXN', 'text', 'Moneda principal.'],
            'logo_principal' => ['', 'url', 'Ruta o URL del logo principal.'],
            'logo_oscuro' => ['', 'url', 'Ruta o URL del logo para fondos oscuros.'],
            'favicon' => ['', 'url', 'Ruta o URL del favicon.'],
            'color_principal' => ['#C9A227', 'text', 'Color base de marca.'],
            'color_secundario' => ['#050505', 'text', 'Color secundario de marca.'],
            'permitir_compra_invitado' => ['1', 'boolean', 'Habilita compras sin registro.'],
            'comision_servicio' => ['0', 'number', 'Comision de servicio.'],
            'impuesto_porcentaje' => ['0', 'number', 'Porcentaje de impuesto.'],
            'max_boletos_orden' => ['10', 'integer', 'Limite de boletos por orden.'],
            'minutos_expiracion_orden' => ['30', 'integer', 'Vigencia de una orden pendiente.'],
            'comprador_correo_obligatorio' => ['1', 'boolean', 'Requiere correo del comprador.'],
            'comprador_telefono_obligatorio' => ['1', 'boolean', 'Requiere telefono del comprador.'],
            'paypal_modo' => ['sandbox', 'select', 'Modo operativo de PayPal.'],
            'prefijo_folio_orden' => ['ORD', 'text', 'Prefijo para folios de orden.'],
            'prefijo_folio_boleto' => ['BOL', 'text', 'Prefijo para folios de boleto.'],
            'permitir_validacion_folio' => ['1', 'boolean', 'Permite validar por folio.'],
            'permitir_validacion_qr' => ['1', 'boolean', 'Permite validar por QR.'],
            'boleto_texto_legal' => ['', 'textarea', 'Texto legal mostrado en el boleto.'],
            'boleto_instrucciones' => ['', 'textarea', 'Instrucciones para el asistente.'],
            'mostrar_eventos_finalizados' => ['0', 'boolean', 'Muestra eventos finalizados.'],
            'home_destacados_limite' => ['5', 'integer', 'Cantidad de destacados en Home.'],
            'eventos_por_pagina' => ['12', 'integer', 'Cantidad de eventos por pagina.'],
            'eventos_orden_default' => ['recientes', 'select', 'Orden por defecto de eventos.'],
            'permitir_eventos_sin_imagen' => ['1', 'boolean', 'Permite eventos sin imagen.'],
        ];
    }
}
