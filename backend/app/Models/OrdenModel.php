<?php

namespace App\Models;

use CodeIgniter\Model;

class OrdenModel extends Model
{
    protected $table            = 'ordenes';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'usuario_id',
        'folio',
        'nombre_comprador',
        'correo_comprador',
        'telefono_comprador',
        'subtotal',
        'comision',
        'total',
        'estado',
        'created_at',
        'updated_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = false;
}
