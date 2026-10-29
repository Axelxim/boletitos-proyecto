<?php

namespace App\Models;

use CodeIgniter\Model;

class ValidacionBoletoModel extends Model
{
    protected $table            = 'validaciones_boletos';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'boleto_id',
        'usuario_validador_id',
        'resultado',
        'observacion',
        'fecha_validacion',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = false;
}
