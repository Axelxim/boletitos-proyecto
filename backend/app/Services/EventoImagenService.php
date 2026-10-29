<?php

namespace App\Services;

use App\Models\EventoImagenModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\App;

class EventoImagenService
{
    private const TIPOS_VALIDOS = ['portada', 'banner', 'galeria', 'miniatura'];
    private const ESTADO_ACTIVO = 'activo';
    private const ESTADO_INACTIVO = 'inactivo';
    private const UPLOAD_DIRECTORY = 'uploads/eventos';

    public function __construct(
        private readonly EventoImagenModel $eventoImagenModel = new EventoImagenModel(),
        private readonly EventoService $eventoService = new EventoService(),
        private readonly BitacoraService $bitacoraService = new BitacoraService()
    ) {
    }

    public function eventoExiste(int $eventoId): bool
    {
        return $this->eventoService->obtenerPorIdAdmin($eventoId) !== null;
    }

    public function obtenerPublicasPorEvento(int $eventoId): array
    {
        return array_map(
            [$this, 'transformarImagen'],
            $this->eventoImagenModel
                ->where('evento_id', $eventoId)
                ->where('estado', self::ESTADO_ACTIVO)
                ->orderBy('principal', 'DESC')
                ->orderBy('orden', 'ASC')
                ->orderBy('id', 'ASC')
                ->findAll()
        );
    }

    public function obtenerAdminPorEvento(int $eventoId): array
    {
        return array_map(
            [$this, 'transformarImagen'],
            $this->eventoImagenModel
                ->where('evento_id', $eventoId)
                ->orderBy('principal', 'DESC')
                ->orderBy('orden', 'ASC')
                ->orderBy('id', 'ASC')
                ->findAll()
        );
    }

    public function obtenerAdminPorId(int $eventoId, int $imagenId): ?array
    {
        $imagen = $this->eventoImagenModel
            ->where('id', $imagenId)
            ->where('evento_id', $eventoId)
            ->first();

        return $imagen ? $this->transformarImagen($imagen) : null;
    }

    public function crear(int $eventoId, UploadedFile $archivo, array $data): ?array
    {
        $nombreArchivo = $this->generarNombreSeguro($eventoId, $archivo);
        $rutaRelativa = self::UPLOAD_DIRECTORY . '/' . $nombreArchivo;
        $rutaDestino = ROOTPATH . 'public/' . self::UPLOAD_DIRECTORY;

        if (! is_dir($rutaDestino) && ! mkdir($rutaDestino, 0775, true) && ! is_dir($rutaDestino)) {
            return null;
        }

        if (! $archivo->move($rutaDestino, $nombreArchivo)) {
            return null;
        }

        $payload = $this->prepararPayload($eventoId, $data, false);
        $payload['ruta'] = $rutaRelativa;

        if ((int) $payload['principal'] === 1) {
            $this->desmarcarPrincipales($eventoId);
        }

        $inserted = $this->eventoImagenModel->insert($payload);

        if ($inserted === false) {
            $this->eliminarArchivoFisico($rutaRelativa);

            return null;
        }

        $imagen = $this->obtenerAdminPorId($eventoId, (int) $inserted);

        $this->bitacoraService->registrar([
            'usuario_id'   => get_authenticated_user()['id'] ?? null,
            'modulo'       => 'evento_imagenes',
            'accion'       => 'create',
            'descripcion'  => 'Imagen de evento creada correctamente',
            'datos_nuevos' => $imagen,
        ]);

        return $imagen;
    }

    public function actualizar(int $eventoId, int $imagenId, array $data): ?array
    {
        $imagenActual = $this->obtenerAdminPorId($eventoId, $imagenId);

        if ($imagenActual === null) {
            return null;
        }

        $payload = $this->prepararPayload($eventoId, $data, true);

        if ((int) ($payload['principal'] ?? 0) === 1) {
            $this->desmarcarPrincipales($eventoId, $imagenId);
        }

        $updated = $this->eventoImagenModel->update($imagenId, $payload);

        if ($updated === false) {
            return null;
        }

        $imagenNueva = $this->obtenerAdminPorId($eventoId, $imagenId);

        $this->bitacoraService->registrar([
            'usuario_id'       => get_authenticated_user()['id'] ?? null,
            'modulo'           => 'evento_imagenes',
            'accion'           => 'update',
            'descripcion'      => 'Imagen de evento actualizada correctamente',
            'datos_anteriores' => $imagenActual,
            'datos_nuevos'     => $imagenNueva,
        ]);

        return $imagenNueva;
    }

    public function eliminar(int $eventoId, int $imagenId): bool
    {
        $imagenActual = $this->obtenerAdminPorId($eventoId, $imagenId);

        if ($imagenActual === null) {
            return false;
        }

        $updated = $this->eventoImagenModel->update($imagenId, [
            'estado'     => self::ESTADO_INACTIVO,
            'principal'  => 0,
            'updated_at' => app_timestamp(),
        ]);

        if ($updated === false) {
            return false;
        }

        $this->eliminarArchivoFisico((string) ($imagenActual['ruta'] ?? ''));

        $this->bitacoraService->registrar([
            'usuario_id'       => get_authenticated_user()['id'] ?? null,
            'modulo'           => 'evento_imagenes',
            'accion'           => 'delete',
            'descripcion'      => 'Imagen de evento eliminada correctamente',
            'datos_anteriores' => $imagenActual,
        ]);

        return true;
    }

    public function tipoEsValido(string $tipo): bool
    {
        return in_array($tipo, self::TIPOS_VALIDOS, true);
    }

    public function tiposValidos(): array
    {
        return self::TIPOS_VALIDOS;
    }

    public function transformarImagen(array $imagen): array
    {
        return [
            'id'         => $imagen['id'],
            'evento_id'  => $imagen['evento_id'],
            'ruta'       => $imagen['ruta'],
            'url'        => $this->generarUrlPublica((string) $imagen['ruta']),
            'tipo'       => $imagen['tipo'],
            'principal'  => (int) $imagen['principal'],
            'orden'      => (int) $imagen['orden'],
            'estado'     => $imagen['estado'],
            'created_at' => $imagen['created_at'],
            'updated_at' => $imagen['updated_at'],
        ];
    }

    private function prepararPayload(int $eventoId, array $data, bool $isUpdate): array
    {
        $payload = [
            'evento_id'   => $eventoId,
            'tipo'        => sanitize_simple_string($data['tipo'] ?? 'galeria'),
            'principal'   => (int) ($data['principal'] ?? 0) === 1 ? 1 : 0,
            'orden'       => max(0, (int) ($data['orden'] ?? 0)),
            'estado'      => sanitize_simple_string($data['estado'] ?? self::ESTADO_ACTIVO),
            'updated_at'  => app_timestamp(),
        ];

        if (! $isUpdate) {
            $payload['created_at'] = app_timestamp();
        }

        return $payload;
    }

    private function desmarcarPrincipales(int $eventoId, ?int $exceptoImagenId = null): void
    {
        $builder = $this->eventoImagenModel
            ->where('evento_id', $eventoId)
            ->where('principal', 1);

        if ($exceptoImagenId !== null) {
            $builder->where('id !=', $exceptoImagenId);
        }

        $builder->set(['principal' => 0, 'updated_at' => app_timestamp()])->update();
    }

    private function generarNombreSeguro(int $eventoId, UploadedFile $archivo): string
    {
        $extension = strtolower($archivo->getClientExtension() ?: $archivo->getExtension() ?: 'jpg');

        return sprintf(
            'evt_%d_%s_%s.%s',
            $eventoId,
            app_timestamp('YmdHis'),
            bin2hex(random_bytes(4)),
            $extension
        );
    }

    private function generarUrlPublica(string $rutaRelativa): string
    {
        $baseUrl = rtrim(config(App::class)->baseURL, '/');

        return $baseUrl . '/' . ltrim($rutaRelativa, '/');
    }

    private function eliminarArchivoFisico(string $rutaRelativa): void
    {
        if ($rutaRelativa === '') {
            return;
        }

        $rutaAbsoluta = ROOTPATH . 'public/' . ltrim($rutaRelativa, '/');

        if (is_file($rutaAbsoluta)) {
            unlink($rutaAbsoluta);
        }
    }
}
