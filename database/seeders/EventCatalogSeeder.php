<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventTable;
use Illuminate\Database\Seeder;

class EventCatalogSeeder extends Seeder
{
  public function run(): void
  {
    $event = Event::firstOrCreate(
      ['code' => config('xv.default_event_code', 'XV-NATALIA')],
      [
        'honoree_name' => 'Natalia Isabella',
        'subtitle' => 'Gala de 15 Años · Recepción',
        'venue' => 'Salón Las Marías · Urb. Los Modines calle 90D #76-61',
        'party_celebrations_count' => 0,
      ]
    );

    $catalog = [
      ['slug' => 'mesa-01', 'number' => '01', 'code' => 'Mesa 01 · Principal', 'zone' => 'Tarima de Honor · Frente a Pista', 'centerpiece' => 'Rosas blancas e iluminación cálida'],
      ['slug' => 'mesa-02', 'number' => '02', 'code' => 'Mesa 02 · Orquídea', 'zone' => 'Ala Norte · Jardín Interior', 'centerpiece' => 'Orquídeas imperiales y cristal'],
      ['slug' => 'mesa-03', 'number' => '03', 'code' => 'Mesa 03 · Magnolia', 'zone' => 'Ala Norte · Jardín Interior', 'centerpiece' => 'Magnolias silvestres y velas altas'],
      ['slug' => 'mesa-04', 'number' => '04', 'code' => 'Mesa 04 · Esmeralda', 'zone' => 'Sector Central · Pista de Vals', 'centerpiece' => 'Follaje esmeralda y candelabro'],
      ['slug' => 'mesa-05', 'number' => '05', 'code' => 'Mesa 05 · Laurel', 'zone' => 'Sector Central · Pista de Vals', 'centerpiece' => 'Ramas de laurel y rosas marfil'],
      ['slug' => 'mesa-06', 'number' => '06', 'code' => 'Mesa 06 · Jazmín', 'zone' => 'Terraza Cubierta · Lounge', 'centerpiece' => 'Jazmines aromáticos y piedra oscura'],
    ];

    foreach ($catalog as $row) {
      EventTable::updateOrCreate(
        ['event_id' => $event->id, 'slug' => $row['slug']],
        array_merge($row, ['capacity' => config('xv.table_default_capacity', 10)])
      );
    }
  }
}
