<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCorreoVerificadoAtToUsuarios extends Migration
{
    public function up()
    {
        $fields = [
            'correo_verificado_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'estado',
            ],
        ];

        if (! $this->db->fieldExists('correo_verificado_at', 'usuarios')) {
            $this->forge->addColumn('usuarios', $fields);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('correo_verificado_at', 'usuarios')) {
            $this->forge->dropColumn('usuarios', 'correo_verificado_at');
        }
    }
}
