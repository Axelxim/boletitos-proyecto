<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class EventosPruebaSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');
        $this->db->transStart();

        $categories = [
            'Concierto' => 'Eventos musicales y presentaciones en vivo',
            'Teatro' => 'Obras, musicales y espectaculos escenicos',
            'Deportes' => 'Competencias y encuentros deportivos',
            'Conferencia' => 'Charlas, congresos y encuentros profesionales',
        ];

        foreach ($categories as $name => $description) {
            $this->insertIfMissing('categorias', 'nombre', $name, [
                'nombre' => $name,
                'descripcion' => $description,
                'estado' => 'activo',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $venues = [
            'Foro Boletitos' => ['Av. Convencion 1200', 'Aguascalientes', 1200],
            'Teatro Central' => ['Calle Cultura 210', 'Aguascalientes', 650],
            'Arena Norte' => ['Blvd. Universidad 850', 'Aguascalientes', 5000],
            'Centro de Convenciones' => ['Av. Siglo XXI 400', 'Aguascalientes', 2000],
        ];

        foreach ($venues as $name => [$address, $city, $capacity]) {
            $this->insertIfMissing('sedes', 'nombre', $name, [
                'nombre' => $name,
                'direccion' => $address,
                'ciudad' => $city,
                'estado' => 'Aguascalientes',
                'pais' => 'Mexico',
                'capacidad' => $capacity,
                'estado_sede' => 'activo',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $events = [
            [
                'category' => 'Concierto',
                'venue' => 'Foro Boletitos',
                'title' => 'Noche de Rock Bajo las Estrellas',
                'description' => 'Una noche de musica en vivo con bandas locales y artistas invitados.',
                'start' => '2026-12-05 20:00:00',
                'end' => '2026-12-05 23:30:00',
                'tickets' => [['General', 450.00, 700, 6], ['Preferente', 750.00, 300, 4]],
            ],
            [
                'category' => 'Teatro',
                'venue' => 'Teatro Central',
                'title' => 'El Ultimo Acto',
                'description' => 'Puesta en escena contemporanea con una historia de misterio y redencion.',
                'start' => '2026-12-12 19:00:00',
                'end' => '2026-12-12 21:00:00',
                'tickets' => [['Platea', 380.00, 450, 6], ['Palco', 620.00, 120, 4]],
            ],
            [
                'category' => 'Deportes',
                'venue' => 'Arena Norte',
                'title' => 'Copa Urbana de Futbol',
                'description' => 'Final regional con acceso digital y actividades para toda la familia.',
                'start' => '2027-01-16 17:00:00',
                'end' => '2027-01-16 20:00:00',
                'tickets' => [['Grada General', 250.00, 3500, 8], ['Zona Preferente', 500.00, 800, 6]],
            ],
            [
                'category' => 'Conferencia',
                'venue' => 'Centro de Convenciones',
                'title' => 'Foro de Innovacion Regional',
                'description' => 'Conferencias y talleres sobre tecnologia, emprendimiento e industrias creativas.',
                'start' => '2027-02-06 09:00:00',
                'end' => '2027-02-06 18:00:00',
                'tickets' => [['Acceso General', 600.00, 1200, 5], ['Pase Completo', 1100.00, 300, 3]],
            ],
        ];

        foreach ($events as $event) {
            $existing = $this->db->table('eventos')->where('titulo', $event['title'])->get()->getRowArray();

            if ($existing) {
                continue;
            }

            $category = $this->db->table('categorias')->where('nombre', $event['category'])->get()->getRowArray();
            $venue = $this->db->table('sedes')->where('nombre', $event['venue'])->get()->getRowArray();

            $this->db->table('eventos')->insert([
                'categoria_id' => $category['id'],
                'sede_id' => $venue['id'],
                'titulo' => $event['title'],
                'descripcion' => $event['description'],
                'lugar' => $event['venue'],
                'direccion' => $venue['direccion'],
                'fecha_inicio' => $event['start'],
                'fecha_fin' => $event['end'],
                'fecha_evento' => substr($event['start'], 0, 10),
                'hora_evento' => substr($event['start'], 11, 8),
                'estado' => 'publicado',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $eventId = $this->db->insertID();
            $this->db->table('evento_metricas')->insert([
                'evento_id' => $eventId,
                'vistas' => 0,
                'clicks' => 0,
                'busquedas' => 0,
                'ventas' => 0,
                'boletos_vendidos' => 0,
                'score' => 0,
                'updated_at' => $now,
            ]);

            foreach ($event['tickets'] as [$name, $price, $quantity, $maxPerPurchase]) {
                $this->db->table('tipos_boletos')->insert([
                    'evento_id' => $eventId,
                    'nombre' => $name,
                    'descripcion' => 'Acceso ' . strtolower($name) . ' para ' . $event['title'],
                    'precio' => $price,
                    'cantidad_total' => $quantity,
                    'cantidad_disponible' => $quantity,
                    'max_por_compra' => $maxPerPurchase,
                    'estado' => 'activo',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new \RuntimeException('No fue posible crear los eventos de prueba.');
        }
    }

    private function insertIfMissing(string $table, string $column, string $value, array $data): void
    {
        if (! $this->db->table($table)->where($column, $value)->get()->getRowArray()) {
            $this->db->table($table)->insert($data);
        }
    }
}
