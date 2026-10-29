<?php

namespace App\Models;

use CodeIgniter\Model;

class BoletoModel extends Model
{
    protected $table            = 'boletos';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'orden_id',
        'tipo_boleto_id',
        'folio',
        'codigo_qr',
        'estado',
        'fecha_uso',
        'created_at',
        'updated_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = false;
}
