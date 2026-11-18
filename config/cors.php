<?php

return [
  'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

  'allowed_methods' => ['*'],

  'allowed_origins' => ['*'],

  'allowed_origins_patterns' => [],

  'allowed_headers' => [
    'Content-Type',
    'X-Requested-With',
    'Authorization',
    'Accept',
    'Origin',
    'X-Device-Id',
    'X-Actor-Key',
    'X-Host-Pin',
    'X-Socket-ID',
  ],

  'exposed_headers' => [],

  'max_age' => 0,

  'supports_credentials' => false,
];
