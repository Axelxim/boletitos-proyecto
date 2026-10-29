<?php

namespace App\Models;

use CodeIgniter\Model;

class EventoImagenModel extends Model
{
    protected $table            = 'evento_imagenes';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'evento_id',
        'ruta',
        'tipo',
        'principal',
        'orden',
        'estado',
        'created_at',
        'updated_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = false;
}
