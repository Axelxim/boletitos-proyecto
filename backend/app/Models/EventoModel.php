<?php

namespace App\Models;

use CodeIgniter\Model;

class EventoModel extends Model
{
    protected $table            = 'eventos';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'categoria_id',
        'sede_id',
        'titulo',
        'descripcion',
        'lugar',
        'direccion',
        'fecha_inicio',
        'fecha_fin',
        'fecha_evento',
        'hora_evento',
        'imagen',
        'estado',
        'created_at',
        'updated_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = false;
}
