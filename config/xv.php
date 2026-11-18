<?php

return [
  'default_event_code' => env('XV_DEFAULT_EVENT_CODE', 'XV-NATALIA'),
  'host_pin' => (string) env('XV_HOST_PIN', '1515'),
  'media_disk' => env('XV_MEDIA_DISK', 'public'),
  'db_scope_prefix' => env('XV_DB_SCOPE_PREFIX', 'laravel'),
  'table_default_capacity' => (int) env('XV_TABLE_CAPACITY', 10),
];
