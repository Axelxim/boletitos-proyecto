<?php

namespace App\Models;

use CodeIgniter\Model;

class TokenRecuperacionModel extends Model
{
    protected $table            = 'tokens_recuperacion';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'usuario_id',
        'token',
        'tipo',
        'usado',
        'expiracion',
        'created_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = false;
}
