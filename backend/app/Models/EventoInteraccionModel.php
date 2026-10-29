<?php

namespace App\Models;

use CodeIgniter\Model;

class EventoInteraccionModel extends Model
{
    protected $table            = 'evento_interacciones';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'evento_id',
        'tipo',
        'ip',
        'user_agent',
        'created_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = false;
}
