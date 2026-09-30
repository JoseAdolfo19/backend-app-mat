<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Límites de peticiones
    |--------------------------------------------------------------------------
    |
    | La aplicación emite entre 3 y 6 llamadas a la API por cada navegación de
    | página. Con 60 peticiones por minuto el cupo se agotaba en uso normal y
    | el frontend recibía 429, que era interpretado como sesión inválida y
    | expulsaba al usuario al login.
    |
    | Estos valores son un límite de abuso, no un límite de negocio. El
    | forcejeo de credenciales sigue protegido aparte, con `throttle` en las
    | rutas de autenticación (ver routes/api.php).
    |
    */

    'global' => [
        'max_attempts' => (int) (env('GLOBAL_RATE_LIMIT_MAX', 120)),
        'decay_minutes' => (int) (env('GLOBAL_RATE_LIMIT_DECAY_MINUTES', 1)),
    ],

    'api' => [
        'max_attempts' => (int) (env('API_RATE_LIMIT_MAX', 120)),
        'decay_minutes' => (int) (env('API_RATE_LIMIT_DECAY_MINUTES', 1)),
    ],

];
