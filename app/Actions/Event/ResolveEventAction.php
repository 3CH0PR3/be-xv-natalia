<?php

namespace App\Actions\Event;

use App\Models\Event;

class ResolveEventAction
{
  public function execute(?string $eventCode = null): Event
  {
    $code = trim((string) ($eventCode ?: config('xv.default_event_code', 'XV-NATALIA')));

    return Event::firstOrCreate(
      ['code' => $code],
      [
        'honoree_name' => 'Natalia Isabella',
        'subtitle' => 'Gala de 15 Años · Recepción',
        'venue' => 'Salón Las Marías · Urb. Los Modines calle 90D #76-61',
        'party_celebrations_count' => 0,
      ]
    );
  }
}
