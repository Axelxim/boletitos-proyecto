<?php

namespace App\Services;

use App\Models\RolModel;

class RolService
{
    public function __construct(private readonly RolModel $rolModel = new RolModel())
    {
    }

    public function obtenerTodos(): array
    {
        return $this->rolModel
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        $rol = $this->rolModel->find($id);

        return $rol ?: null;
    }
}
