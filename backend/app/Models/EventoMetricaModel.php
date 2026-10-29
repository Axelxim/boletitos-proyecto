<?php

namespace App\Models;

use CodeIgniter\Model;

class EventoMetricaModel extends Model
{
    protected $table            = 'evento_metricas';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'evento_id',
        'vistas',
        'clicks',
        'busquedas',
        'ventas',
        'boletos_vendidos',
        'score',
        'updated_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = false;
}
