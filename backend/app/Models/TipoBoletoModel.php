<?php

namespace App\Models;

use CodeIgniter\Model;

class TipoBoletoModel extends Model
{
    protected $table            = 'tipos_boletos';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'evento_id',
        'nombre',
        'descripcion',
        'precio',
        'cantidad_total',
        'cantidad_disponible',
        'max_por_compra',
        'estado',
        'created_at',
        'updated_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = false;
}
