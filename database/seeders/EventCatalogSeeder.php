<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventTable;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
      ['slug' => 'mesa-01', 'number' => '01', 'code' => 'Mesa 1'],
      ['slug' => 'mesa-02', 'number' => '02', 'code' => 'Mesa 2'],
      ['slug' => 'mesa-03', 'number' => '03', 'code' => 'Mesa 3'],
      ['slug' => 'mesa-04', 'number' => '04', 'code' => 'Mesa 4'],
      ['slug' => 'mesa-05', 'number' => '05', 'code' => 'Mesa 5'],
      ['slug' => 'mesa-06', 'number' => '06', 'code' => 'Mesa 6'],
    ];

    foreach ($catalog as $row) {
      EventTable::updateOrCreate(
        ['event_id' => $event->id, 'slug' => $row['slug']],
        array_merge($row, ['capacity' => config('xv.table_default_capacity', 10)])
      );
    }

    User::firstOrCreate(
      ['email' => config('xv.host_email', 'anfitriona@xv-natalia-isabela.com')],
      [
        'name' => 'Anfitriona XV',
        'password' => Hash::make(config('xv.host_pin', '1515')),
      ]
    );
  }
}
