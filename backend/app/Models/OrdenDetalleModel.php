<?php

namespace App\Models;

use CodeIgniter\Model;

class OrdenDetalleModel extends Model
{
    protected $table            = 'orden_detalles';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'orden_id',
        'tipo_boleto_id',
        'cantidad',
        'precio_unitario',
        'subtotal',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = false;
}
