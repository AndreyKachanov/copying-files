<?php

use Illuminate\Support\Facades\Crypt;

return [
    'oracle' => [
        'driver'         => 'oracle',
        'tns'            => env('DB_TNS', ''),
        'host'           => env('DB_HOST', ''),
        'port'           => env('DB_PORT', '1521'),
        'database'       => Crypt::decrypt(env('DB_DATABASE', '')),
        'service_name'   => env('DB_SERVICENAME', ''),
        'username'       => Crypt::decrypt(env('DB_USERNAME', '')),
        'password'       => Crypt::decrypt(env('DB_PASSWORD', '')),
        'charset'        => env('DB_CHARSET', 'AL32UTF8'),
        'prefix'         => env('DB_PREFIX', ''),
        'prefix_schema'  => env('DB_SCHEMA_PREFIX', ''),
        'edition'        => env('DB_EDITION', 'ora$base'),
        'server_version' => env('DB_SERVER_VERSION', '12c'),
        'dynamic'        => [],
    ],
];
