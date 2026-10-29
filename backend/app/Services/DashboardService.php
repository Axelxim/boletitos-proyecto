<?php

namespace App\Services;

use App\Models\BoletoModel;
use App\Models\EventoModel;
use App\Models\OrdenModel;

class DashboardService
{
    public function __construct(
        private readonly EventoModel $eventoModel = new EventoModel(),
        private readonly OrdenModel $ordenModel = new OrdenModel(),
        private readonly BoletoModel $boletoModel = new BoletoModel()
    ) {
    }

    public function obtenerResumen(): array
    {
        return [
            'total_eventos'           => $this->eventoModel->countAllResults(),
            'eventos_publicados'      => $this->eventoModel->where('estado', 'publicado')->countAllResults(),
            'total_ordenes'           => $this->ordenModel->countAllResults(),
            'ordenes_pagadas'         => $this->ordenModel->where('estado', 'pagada')->countAllResults(),
            'total_ventas'            => (float) ($this->ordenModel->selectSum('total')->where('estado', 'pagada')->first()['total'] ?? 0),
            'total_boletos_generados' => $this->boletoModel->countAllResults(),
            'boletos_vigentes'        => $this->boletoModel->where('estado', 'vigente')->countAllResults(),
            'boletos_usados'          => $this->boletoModel->where('estado', 'usado')->countAllResults(),
            'boletos_cancelados'      => $this->boletoModel->where('estado', 'cancelado')->countAllResults(),
        ];
    }
}
