<?php

namespace App\Services;

use App\Models\EventoInteraccionModel;
use App\Models\EventoMetricaModel;

class EventoMetricaService
{
    private const TIPOS_VALIDOS = ['vista', 'click', 'busqueda', 'compra'];

    public function __construct(
        private readonly EventoService $eventoService = new EventoService(),
        private readonly EventoMetricaModel $eventoMetricaModel = new EventoMetricaModel(),
        private readonly EventoInteraccionModel $eventoInteraccionModel = new EventoInteraccionModel()
    ) {
    }

    public function eventoExiste(int $eventoId): bool
    {
        return $this->eventoService->obtenerPorIdAdmin($eventoId) !== null;
    }

    public function tipoEsValido(string $tipo): bool
    {
        return in_array($tipo, self::TIPOS_VALIDOS, true);
    }

    public function registrarInteraccion(int $eventoId, string $tipo): ?array
    {
        $tipo = sanitize_simple_string($tipo);

        if (! $this->eventoExiste($eventoId) || ! $this->tipoEsValido($tipo)) {
            return null;
        }

        $db = $this->eventoMetricaModel->db;
        $db->transStart();

        $this->eventoInteraccionModel->insert([
            'evento_id'   => $eventoId,
            'tipo'        => $tipo,
            'ip'          => client_ip_address(),
            'user_agent'  => client_user_agent(),
            'created_at'  => app_timestamp(),
        ]);

        $metrica = $this->eventoMetricaModel->where('evento_id', $eventoId)->first();

        if ($metrica === null) {
            $metrica = [
                'evento_id'         => $eventoId,
                'vistas'            => 0,
                'clicks'            => 0,
                'busquedas'         => 0,
                'ventas'            => 0,
                'boletos_vendidos'  => 0,
                'score'             => 0,
                'updated_at'        => app_timestamp(),
            ];

            $metricaId = $this->eventoMetricaModel->insert($metrica, true);

            if ($metricaId === false) {
                $db->transRollback();

                return null;
            }

            $metrica['id'] = $metricaId;
        }

        $contadorCampo = $this->resolverCampoContador($tipo);
        $metrica[$contadorCampo] = (int) ($metrica[$contadorCampo] ?? 0) + 1;
        $metrica['score'] = $this->calcularScore($metrica);
        $metrica['updated_at'] = app_timestamp();

        $updated = $this->eventoMetricaModel->update((int) $metrica['id'], [
            $contadorCampo      => $metrica[$contadorCampo],
            'score'             => $metrica['score'],
            'updated_at'        => $metrica['updated_at'],
        ]);

        if ($updated === false) {
            $db->transRollback();

            return null;
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            return null;
        }

        return $this->obtenerMetricaPorEvento($eventoId);
    }

    public function obtenerMetricaPorEvento(int $eventoId): ?array
    {
        $metrica = $this->eventoMetricaModel->where('evento_id', $eventoId)->first();

        return $metrica ?: null;
    }

    public function obtenerDestacados(int $limit = 5): array
    {
        return $this->eventoService->obtenerDestacadosPorScore($limit);
    }

    private function resolverCampoContador(string $tipo): string
    {
        return match ($tipo) {
            'vista' => 'vistas',
            'click' => 'clicks',
            'busqueda' => 'busquedas',
            'compra' => 'ventas',
            default => 'vistas',
        };
    }

    private function calcularScore(array $metrica): float
    {
        $ventas = (int) ($metrica['ventas'] ?? 0);
        $clicks = (int) ($metrica['clicks'] ?? 0);
        $busquedas = (int) ($metrica['busquedas'] ?? 0);
        $vistas = (int) ($metrica['vistas'] ?? 0);

        return ($ventas * 5) + ($clicks * 2) + ($busquedas * 1) + ($vistas * 0.5);
    }
}
