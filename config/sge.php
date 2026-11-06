<?php

return [
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
    'subdominio_plataforma' => env('PLATFORM_SUBDOMAIN', 'plataforma'),
    'jerarquia_roles' => [
        'Superadministrador' => 100,
        'Director' => 80,
        'Administrador' => 60,
        'Tesorería' => 40,
        'Coordinación' => 40,
        'Docente' => 20,
        'Auxiliar' => 20,
        'Apoderado' => 10,
    ],
];
