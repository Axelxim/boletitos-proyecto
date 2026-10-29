<?php

namespace App\Services;

use App\Models\UsuarioModel;
use CodeIgniter\Database\BaseBuilder;

class UsuarioService
{
    public function __construct(private readonly UsuarioModel $usuarioModel = new UsuarioModel())
    {
    }

    public function obtenerTodos(): array
    {
        $usuarios = $this->baseConsultaConRol()
            ->orderBy('usuarios.id', 'ASC')
            ->findAll();

        return array_map([$this, 'transformarUsuario'], $usuarios);
    }

    public function obtenerPorId(int $id): ?array
    {
        $usuario = $this->baseConsultaConRol()
            ->where('usuarios.id', $id)
            ->first();

        return $usuario ? $this->transformarUsuario($usuario) : null;
    }

    public function obtenerPorCorreo(string $correo): ?array
    {
        $usuario = $this->baseConsultaConRol()
            ->where('usuarios.correo', $correo)
            ->first();

        return $usuario ?: null;
    }

    public function crear(array $data): ?array
    {
        $inserted = $this->usuarioModel->insert($data);

        if ($inserted === false) {
            return null;
        }

        return $this->obtenerPorId((int) $inserted);
    }

    public function transformarUsuario(array $usuario): array
    {
        return [
            'id'                   => $usuario['id'],
            'rol_id'               => $usuario['rol_id'],
            'nombre'               => $usuario['nombre'],
            'apellido'             => $usuario['apellido'],
            'correo'               => $usuario['correo'],
            'telefono'             => $usuario['telefono'],
            'estado'               => $usuario['estado'],
            'correo_verificado_at' => $usuario['correo_verificado_at'] ?? null,
            'created_at'           => $usuario['created_at'],
            'updated_at'           => $usuario['updated_at'],
            'rol'                  => $usuario['rol_id'] ? [
                'id'          => $usuario['rol_id'],
                'nombre'      => $usuario['rol_nombre'],
                'descripcion' => $usuario['rol_descripcion'],
            ] : null,
        ];
    }

    public function actualizar(int $id, array $data): ?array
    {
        $updated = $this->usuarioModel->update($id, $data);

        if ($updated === false) {
            return null;
        }

        return $this->obtenerPorId($id);
    }

    private function baseConsultaConRol(): UsuarioModel
    {
        return $this->usuarioModel
            ->select('usuarios.id, usuarios.rol_id, usuarios.nombre, usuarios.apellido, usuarios.correo, usuarios.telefono, usuarios.estado, usuarios.correo_verificado_at, usuarios.created_at, usuarios.updated_at, usuarios.password, roles.nombre as rol_nombre, roles.descripcion as rol_descripcion')
            ->join('roles', 'roles.id = usuarios.rol_id', 'left');
    }
}
