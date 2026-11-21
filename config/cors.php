<?php

return [

  /*
  |--------------------------------------------------------------------------
  | Cross-Origin Resource Sharing (CORS) Configuration
  |--------------------------------------------------------------------------
  |
  | Restringido exclusivamente al dominio oficial del evento y entornos locales.
  | Arquitectura idéntica a be-gea-wrc.
  |
  */

  'paths' => [
    'api/*',
    'sanctum/csrf-cookie',
    'broadcasting/*',
    'storage/*',
  ],

  'allowed_methods' => ['*'],

  'allowed_origins' => [
    'http://localhost:3000',
    'https://localhost:3000',
    'http://127.0.0.1:3000',
    'https://127.0.0.1:3000',
    'http://localhost:5173',
    'https://localhost:5173',
    'http://localhost:4173',
    'https://xv-natalia-isabela.com',
    'https://www.xv-natalia-isabela.com',
  ],

  'allowed_origins_patterns' => [
    '/^https:\/\/.*\.xv-natalia-isabela\.com$/',
    '/^https?:\/\/.*\.localhost(:\d+)?$/',
    '/^https?:\/\/.*\.trycloudflare\.com$/',
    '/^https?:\/\/(?:127\.0\.0\.1|localhost)(?::\d+)?$/',
    '/^https?:\/\/(?:192\.168\.\d+\.\d+|10\.\d+\.\d+\.\d+|172\.(?:1[6-9]|2\d|3[01])\.\d+\.\d+)(?::\d+)?$/',
  ],

  'allowed_headers' => ['*'],

  'exposed_headers' => [],

  'max_age' => 0,

  'supports_credentials' => true,

];
