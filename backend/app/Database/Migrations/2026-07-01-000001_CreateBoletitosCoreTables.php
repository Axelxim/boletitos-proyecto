<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBoletitosCoreTables extends Migration
{
    public function up()
    {
        $this->createRoles();
        $this->createUsuarios();
        $this->createTokensRecuperacion();
        $this->createCategorias();
        $this->createSedes();
        $this->createEventos();
        $this->createEventoImagenes();
        $this->createTiposBoletos();
        $this->createOrdenes();
        $this->createOrdenDetalles();
        $this->createPagos();
        $this->createBoletos();
        $this->createValidacionesBoletos();
        $this->createBitacora();
        $this->createConfiguracion();
    }

    public function down()
    {
        foreach ([
            'configuracion',
            'bitacora',
            'validaciones_boletos',
            'boletos',
            'pagos',
            'orden_detalles',
            'ordenes',
            'tipos_boletos',
            'evento_imagenes',
            'eventos',
            'sedes',
            'categorias',
            'tokens_recuperacion',
            'usuarios',
            'roles',
        ] as $table) {
            $this->forge->dropTable($table, true);
        }
    }

    private function createRoles(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'nombre' => $this->varchar(50),
            'descripcion' => $this->text(true),
            'created_at' => $this->dateTime(true),
            'updated_at' => $this->dateTime(true),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('nombre');
        $this->forge->createTable('roles', true);
    }

    private function createUsuarios(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'rol_id' => $this->unsignedInt(),
            'nombre' => $this->varchar(100),
            'apellido' => $this->varchar(100, true),
            'correo' => $this->varchar(190),
            'password' => $this->varchar(255),
            'telefono' => $this->varchar(20, true),
            'estado' => $this->varchar(20, false, 'activo'),
            'created_at' => $this->dateTime(true),
            'updated_at' => $this->dateTime(true),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('rol_id');
        $this->forge->addUniqueKey('correo');
        $this->forge->addForeignKey('rol_id', 'roles', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('usuarios', true);
    }

    private function createTokensRecuperacion(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'usuario_id' => $this->unsignedInt(),
            'token' => $this->varchar(255),
            'tipo' => $this->varchar(40),
            'usado' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'expiracion' => $this->dateTime(),
            'created_at' => $this->dateTime(true),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['usuario_id', 'tipo']);
        $this->forge->addUniqueKey('token');
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('tokens_recuperacion', true);
    }

    private function createCategorias(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'nombre' => $this->varchar(100),
            'descripcion' => $this->text(true),
            'estado' => $this->varchar(20, false, 'activo'),
            'created_at' => $this->dateTime(true),
            'updated_at' => $this->dateTime(true),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('nombre');
        $this->forge->createTable('categorias', true);
    }

    private function createSedes(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'nombre' => $this->varchar(150),
            'direccion' => $this->varchar(255),
            'ciudad' => $this->varchar(100, true),
            'estado' => $this->varchar(100, true),
            'pais' => $this->varchar(100, true),
            'latitud' => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'longitud' => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'url_mapa' => $this->text(true),
            'capacidad' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'estado_sede' => $this->varchar(20, false, 'activo'),
            'created_at' => $this->dateTime(true),
            'updated_at' => $this->dateTime(true),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('sedes', true);
    }

    private function createEventos(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'categoria_id' => $this->unsignedInt(),
            'sede_id' => $this->unsignedInt(),
            'titulo' => $this->varchar(180),
            'descripcion' => $this->text(true),
            'lugar' => $this->varchar(180),
            'direccion' => $this->varchar(255, true),
            'fecha_inicio' => $this->dateTime(),
            'fecha_fin' => $this->dateTime(),
            'fecha_evento' => ['type' => 'DATE', 'null' => true],
            'hora_evento' => ['type' => 'TIME', 'null' => true],
            'imagen' => $this->varchar(255, true),
            'estado' => $this->varchar(20, false, 'borrador'),
            'created_at' => $this->dateTime(true),
            'updated_at' => $this->dateTime(true),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('categoria_id');
        $this->forge->addKey('sede_id');
        $this->forge->addKey(['estado', 'fecha_inicio']);
        $this->forge->addForeignKey('categoria_id', 'categorias', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('sede_id', 'sedes', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('eventos', true);
    }

    private function createEventoImagenes(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'evento_id' => $this->unsignedInt(),
            'ruta' => $this->varchar(255),
            'tipo' => $this->varchar(20),
            'principal' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'orden' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'estado' => $this->varchar(20, false, 'activo'),
            'created_at' => $this->dateTime(true),
            'updated_at' => $this->dateTime(true),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['evento_id', 'estado']);
        $this->forge->addForeignKey('evento_id', 'eventos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('evento_imagenes', true);
    }

    private function createTiposBoletos(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'evento_id' => $this->unsignedInt(),
            'nombre' => $this->varchar(100),
            'descripcion' => $this->text(true),
            'precio' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'cantidad_total' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'cantidad_disponible' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'max_por_compra' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 1],
            'estado' => $this->varchar(20, false, 'activo'),
            'created_at' => $this->dateTime(true),
            'updated_at' => $this->dateTime(true),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['evento_id', 'estado']);
        $this->forge->addForeignKey('evento_id', 'eventos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('tipos_boletos', true);
    }

    private function createOrdenes(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'usuario_id' => $this->unsignedInt(true),
            'folio' => $this->varchar(50),
            'nombre_comprador' => $this->varchar(150),
            'correo_comprador' => $this->varchar(190),
            'telefono_comprador' => $this->varchar(20, true),
            'subtotal' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'comision' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'total' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'estado' => $this->varchar(20, false, 'pendiente'),
            'created_at' => $this->dateTime(true),
            'updated_at' => $this->dateTime(true),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('folio');
        $this->forge->addKey('usuario_id');
        $this->forge->addKey(['estado', 'created_at']);
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('ordenes', true);
    }

    private function createOrdenDetalles(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'orden_id' => $this->unsignedInt(),
            'tipo_boleto_id' => $this->unsignedInt(),
            'cantidad' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'precio_unitario' => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'subtotal' => ['type' => 'DECIMAL', 'constraint' => '12,2'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('orden_id');
        $this->forge->addKey('tipo_boleto_id');
        $this->forge->addForeignKey('orden_id', 'ordenes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('tipo_boleto_id', 'tipos_boletos', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('orden_detalles', true);
    }

    private function createPagos(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'orden_id' => $this->unsignedInt(),
            'proveedor' => $this->varchar(30),
            'referencia_pago' => $this->varchar(190, true),
            'monto' => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'moneda' => $this->varchar(5, false, 'MXN'),
            'estado' => $this->varchar(30),
            'respuesta_json' => ['type' => 'LONGTEXT', 'null' => true],
            'created_at' => $this->dateTime(true),
            'updated_at' => $this->dateTime(true),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['orden_id', 'proveedor']);
        $this->forge->addKey('referencia_pago');
        $this->forge->addForeignKey('orden_id', 'ordenes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('pagos', true);
    }

    private function createBoletos(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'orden_id' => $this->unsignedInt(),
            'tipo_boleto_id' => $this->unsignedInt(),
            'folio' => $this->varchar(60),
            'codigo_qr' => $this->varchar(190),
            'estado' => $this->varchar(20, false, 'vigente'),
            'fecha_uso' => $this->dateTime(true),
            'created_at' => $this->dateTime(true),
            'updated_at' => $this->dateTime(true),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('folio');
        $this->forge->addUniqueKey('codigo_qr');
        $this->forge->addKey('orden_id');
        $this->forge->addKey('tipo_boleto_id');
        $this->forge->addKey('estado');
        $this->forge->addForeignKey('orden_id', 'ordenes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('tipo_boleto_id', 'tipos_boletos', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('boletos', true);
    }

    private function createValidacionesBoletos(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'boleto_id' => $this->unsignedInt(true),
            'usuario_validador_id' => $this->unsignedInt(true),
            'resultado' => $this->varchar(20),
            'observacion' => $this->text(true),
            'fecha_validacion' => $this->dateTime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('boleto_id');
        $this->forge->addKey('usuario_validador_id');
        $this->forge->addForeignKey('boleto_id', 'boletos', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('usuario_validador_id', 'usuarios', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('validaciones_boletos', true);
    }

    private function createBitacora(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'usuario_id' => $this->unsignedInt(true),
            'modulo' => $this->varchar(100),
            'accion' => $this->varchar(100),
            'descripcion' => $this->text(true),
            'ip' => $this->varchar(45, true),
            'user_agent' => $this->text(true),
            'datos_anteriores' => ['type' => 'LONGTEXT', 'null' => true],
            'datos_nuevos' => ['type' => 'LONGTEXT', 'null' => true],
            'created_at' => $this->dateTime(true),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('usuario_id');
        $this->forge->addKey(['modulo', 'accion']);
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('bitacora', true);
    }

    private function createConfiguracion(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'clave' => $this->varchar(100),
            'valor' => ['type' => 'LONGTEXT', 'null' => true],
            'descripcion' => $this->text(true),
            'tipo' => $this->varchar(30, false, 'text'),
            'created_at' => $this->dateTime(true),
            'updated_at' => $this->dateTime(true),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('clave');
        $this->forge->createTable('configuracion', true);
    }

    private function id(): array
    {
        return [
            'type' => 'INT',
            'constraint' => 11,
            'unsigned' => true,
            'auto_increment' => true,
        ];
    }

    private function unsignedInt(bool $null = false): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => $null];
    }

    private function varchar(int $length, bool $null = false, ?string $default = null): array
    {
        $field = ['type' => 'VARCHAR', 'constraint' => $length, 'null' => $null];

        if ($default !== null) {
            $field['default'] = $default;
        }

        return $field;
    }

    private function text(bool $null = false): array
    {
        return ['type' => 'TEXT', 'null' => $null];
    }

    private function dateTime(bool $null = false): array
    {
        return ['type' => 'DATETIME', 'null' => $null];
    }
}
