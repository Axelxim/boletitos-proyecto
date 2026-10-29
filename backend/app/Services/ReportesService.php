<?php

namespace App\Services;

use App\Models\TipoBoletoModel;

class ReportesService
{
    public function __construct(
        private readonly OrdenService $ordenService = new OrdenService(),
        private readonly TipoBoletoModel $tipoBoletoModel = new TipoBoletoModel()
    ) {
    }

    public function obtenerReportes(array $filters = []): array
    {
        $normalizedFilters = $this->normalizarFiltros($filters);
        $ordenes = $this->filtrarOrdenes(
            $this->ordenService->obtenerTodasAdminEnriquecidas(),
            $normalizedFilters
        );
        $tiposBoletos = $this->obtenerTiposBoletosFiltrados($normalizedFilters);

        return [
            'filtros'                  => $normalizedFilters,
            'resumen_general'          => $this->construirResumenGeneral($ordenes),
            'ventas_por_evento'        => $this->construirVentasPorEvento($ordenes, $tiposBoletos),
            'ventas_por_tipo_boleto'   => $this->construirVentasPorTipoBoleto($ordenes),
            'asistencia'               => $this->construirAsistencia($ordenes),
        ];
    }

    private function normalizarFiltros(array $filters): array
    {
        return [
            'fecha_desde'  => $this->normalizarFecha($filters['fecha_desde'] ?? null),
            'fecha_hasta'  => $this->normalizarFecha($filters['fecha_hasta'] ?? null),
            'evento_id'    => $this->normalizarEnteroPositivo($filters['evento_id'] ?? null),
            'categoria_id' => $this->normalizarEnteroPositivo($filters['categoria_id'] ?? null),
            'estado_orden' => $this->normalizarEstadoOrden($filters['estado_orden'] ?? null),
        ];
    }

    private function filtrarOrdenes(array $ordenes, array $filters): array
    {
        return array_values(array_filter($ordenes, function (array $orden) use ($filters): bool {
            $fechaBase = substr((string) ($orden['created_at'] ?? ''), 0, 10);

            if ($filters['fecha_desde'] !== null && $fechaBase < $filters['fecha_desde']) {
                return false;
            }

            if ($filters['fecha_hasta'] !== null && $fechaBase > $filters['fecha_hasta']) {
                return false;
            }

            if ($filters['estado_orden'] !== null && ($orden['estado'] ?? null) !== $filters['estado_orden']) {
                return false;
            }

            if ($filters['evento_id'] !== null && (int) ($orden['evento']['id'] ?? 0) !== $filters['evento_id']) {
                return false;
            }

            if ($filters['categoria_id'] !== null && (int) ($orden['evento']['categoria_id'] ?? 0) !== $filters['categoria_id']) {
                return false;
            }

            return true;
        }));
    }

    private function obtenerTiposBoletosFiltrados(array $filters): array
    {
        $builder = $this->tipoBoletoModel
            ->select('tipos_boletos.*, eventos.titulo as evento_titulo, eventos.categoria_id as evento_categoria_id')
            ->join('eventos', 'eventos.id = tipos_boletos.evento_id', 'left');

        if ($filters['evento_id'] !== null) {
            $builder->where('tipos_boletos.evento_id', $filters['evento_id']);
        }

        if ($filters['categoria_id'] !== null) {
            $builder->where('eventos.categoria_id', $filters['categoria_id']);
        }

        return $builder->findAll();
    }

    private function construirResumenGeneral(array $ordenes): array
    {
        $ordenesPagadas = array_values(array_filter($ordenes, static fn (array $orden): bool => ($orden['estado'] ?? '') === 'pagada'));
        $ordenesPendientes = array_values(array_filter($ordenes, static fn (array $orden): bool => ($orden['estado'] ?? '') === 'pendiente'));
        $boletos = [];

        foreach ($ordenes as $orden) {
            foreach ($orden['boletos_generados'] ?? [] as $boleto) {
                $boletos[] = $boleto;
            }
        }

        $boletosGenerados = count($boletos);
        $boletosVigentes = count(array_filter($boletos, static fn (array $boleto): bool => ($boleto['estado'] ?? '') === 'vigente'));
        $boletosUsados = count(array_filter($boletos, static fn (array $boleto): bool => ($boleto['estado'] ?? '') === 'usado'));
        $boletosCancelados = count(array_filter($boletos, static fn (array $boleto): bool => ($boleto['estado'] ?? '') === 'cancelado'));
        $totalVentas = round(array_reduce($ordenesPagadas, static fn (float $carry, array $orden): float => $carry + (float) ($orden['total'] ?? 0), 0.0), 2);
        $porcentajeAsistencia = $boletosGenerados > 0 ? round(($boletosUsados / $boletosGenerados) * 100, 2) : 0.0;

        return [
            'total_ventas'           => $totalVentas,
            'ordenes_pagadas'        => count($ordenesPagadas),
            'ordenes_pendientes'     => count($ordenesPendientes),
            'boletos_generados'      => $boletosGenerados,
            'boletos_vigentes'       => $boletosVigentes,
            'boletos_usados'         => $boletosUsados,
            'boletos_cancelados'     => $boletosCancelados,
            'porcentaje_asistencia'  => $porcentajeAsistencia,
        ];
    }

    private function construirVentasPorEvento(array $ordenes, array $tiposBoletos): array
    {
        $rows = [];

        foreach ($tiposBoletos as $tipo) {
            $eventoId = (int) ($tipo['evento_id'] ?? 0);

            if ($eventoId <= 0) {
                continue;
            }

            if (! isset($rows[$eventoId])) {
                $rows[$eventoId] = [
                    'evento_id'             => $eventoId,
                    'evento'                => $tipo['evento_titulo'] ?? 'Evento sin referencia',
                    'ordenes_pagadas'       => 0,
                    'boletos_vendidos'      => 0,
                    'ingresos'              => 0.0,
                    'boletos_disponibles'   => 0,
                    'porcentaje_vendido'    => 0.0,
                ];
            }

            $vendidos = max(0, (int) ($tipo['cantidad_total'] ?? 0) - (int) ($tipo['cantidad_disponible'] ?? 0));
            $rows[$eventoId]['boletos_vendidos'] += $vendidos;
            $rows[$eventoId]['boletos_disponibles'] += (int) ($tipo['cantidad_disponible'] ?? 0);
        }

        foreach ($ordenes as $orden) {
            if (($orden['estado'] ?? '') !== 'pagada') {
                continue;
            }

            $eventoId = (int) ($orden['evento']['id'] ?? 0);

            if ($eventoId <= 0) {
                continue;
            }

            if (! isset($rows[$eventoId])) {
                $rows[$eventoId] = [
                    'evento_id'             => $eventoId,
                    'evento'                => $orden['evento']['titulo'] ?? 'Evento sin referencia',
                    'ordenes_pagadas'       => 0,
                    'boletos_vendidos'      => 0,
                    'ingresos'              => 0.0,
                    'boletos_disponibles'   => 0,
                    'porcentaje_vendido'    => 0.0,
                ];
            }

            $rows[$eventoId]['ordenes_pagadas'] += 1;
            $rows[$eventoId]['ingresos'] += (float) ($orden['total'] ?? 0);
        }

        foreach ($rows as &$row) {
            $inventarioTotal = (int) $row['boletos_vendidos'] + (int) $row['boletos_disponibles'];
            $row['ingresos'] = round((float) $row['ingresos'], 2);
            $row['porcentaje_vendido'] = $inventarioTotal > 0
                ? round(((int) $row['boletos_vendidos'] / $inventarioTotal) * 100, 2)
                : 0.0;
        }
        unset($row);

        usort($rows, static fn (array $a, array $b): int => $b['ingresos'] <=> $a['ingresos']);

        return array_values($rows);
    }

    private function construirVentasPorTipoBoleto(array $ordenes): array
    {
        $rows = [];

        foreach ($ordenes as $orden) {
            if (($orden['estado'] ?? '') !== 'pagada') {
                continue;
            }

            foreach ($orden['detalles'] ?? [] as $detalle) {
                $tipoId = (int) ($detalle['tipo_boleto_id'] ?? 0);

                if ($tipoId <= 0) {
                    continue;
                }

                $key = (string) $tipoId;

                if (! isset($rows[$key])) {
                    $rows[$key] = [
                        'tipo_boleto_id'    => $tipoId,
                        'evento_id'         => (int) ($detalle['evento']['id'] ?? 0),
                        'evento'            => $detalle['evento']['titulo'] ?? 'Evento sin referencia',
                        'tipo_boleto'       => $detalle['tipo_boleto']['nombre'] ?? 'Tipo sin referencia',
                        'cantidad_vendida'  => 0,
                        'precio'            => (float) ($detalle['precio_unitario'] ?? 0),
                        'ingresos'          => 0.0,
                    ];
                }

                $rows[$key]['cantidad_vendida'] += (int) ($detalle['cantidad'] ?? 0);
                $rows[$key]['ingresos'] += (float) ($detalle['subtotal'] ?? 0);
            }
        }

        foreach ($rows as &$row) {
            $row['ingresos'] = round((float) $row['ingresos'], 2);
        }
        unset($row);

        usort($rows, static fn (array $a, array $b): int => $b['ingresos'] <=> $a['ingresos']);

        return array_values($rows);
    }

    private function construirAsistencia(array $ordenes): array
    {
        $rows = [];

        foreach ($ordenes as $orden) {
            $eventoId = (int) ($orden['evento']['id'] ?? 0);

            if ($eventoId <= 0) {
                continue;
            }

            if (! isset($rows[$eventoId])) {
                $rows[$eventoId] = [
                    'evento_id'            => $eventoId,
                    'evento'               => $orden['evento']['titulo'] ?? 'Evento sin referencia',
                    'boletos_emitidos'     => 0,
                    'boletos_usados'       => 0,
                    'boletos_pendientes'   => 0,
                    'porcentaje_acceso'    => 0.0,
                ];
            }

            foreach ($orden['boletos_generados'] ?? [] as $boleto) {
                $rows[$eventoId]['boletos_emitidos'] += 1;

                if (($boleto['estado'] ?? '') === 'usado') {
                    $rows[$eventoId]['boletos_usados'] += 1;
                    continue;
                }

                if (($boleto['estado'] ?? '') === 'vigente') {
                    $rows[$eventoId]['boletos_pendientes'] += 1;
                }
            }
        }

        foreach ($rows as &$row) {
            $row['porcentaje_acceso'] = $row['boletos_emitidos'] > 0
                ? round(($row['boletos_usados'] / $row['boletos_emitidos']) * 100, 2)
                : 0.0;
        }
        unset($row);

        usort($rows, static fn (array $a, array $b): int => $b['porcentaje_acceso'] <=> $a['porcentaje_acceso']);

        return array_values($rows);
    }

    private function normalizarFecha(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    private function normalizarEnteroPositivo(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    private function normalizarEstadoOrden(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = sanitize_simple_string($value);

        return in_array($value, ['pagada', 'pendiente', 'fallida', 'cancelada'], true) ? $value : null;
    }
}
