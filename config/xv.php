<?php

return [
  'default_event_code' => env('XV_DEFAULT_EVENT_CODE', 'XV-NATALIA'),
  'host_pin' => (string) env('XV_HOST_PIN', '1515'),
  'monitoring_pin' => (string) env('XV_MONITORING_PIN', '$*Developer1'),
  'host_email' => env('XV_HOST_EMAIL', 'anfitriona@xv-natalia-isabela.com'),
  'media_disk' => env('XV_MEDIA_DISK', 'public'),
  'max_upload_file_bytes' => (int) env('XV_MAX_UPLOAD_FILE_BYTES', 700 * 1024 * 1024),
  'db_scope_prefix' => env('XV_DB_SCOPE_PREFIX', 'laravel'),
  'table_default_capacity' => (int) env('XV_TABLE_CAPACITY', 10),
  'allow_desktop_capture' => env('APP_ENV') !== 'testing' && (bool) env('ALLOW_DESKTOP_CAPTURE', env('VITE_ALLOW_DESKTOP_CAPTURE', false)),
  'ffmpeg_enabled' => (bool) env('FFMPEG_ENABLED', env('APP_ENV') !== 'testing'),
  'ffmpeg_bin' => env('FFMPEG_BIN', ''),
  'ffprobe_bin' => env('FFPROBE_BIN', ''),
];
