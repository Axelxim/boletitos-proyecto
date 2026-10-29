<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEventoMetricasTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'evento_id' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'vistas' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'clicks' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'busquedas' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'ventas' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'boletos_vendidos' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'score' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'default'    => 0,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('evento_id');
        $this->forge->createTable('evento_metricas');

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'evento_id' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'tipo' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'ip' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
            ],
            'user_agent' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('evento_id');
        $this->forge->createTable('evento_interacciones');
    }

    public function down()
    {
        $this->forge->dropTable('evento_interacciones', true);
        $this->forge->dropTable('evento_metricas', true);
    }
}
