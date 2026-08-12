<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Initial production administrator
    |--------------------------------------------------------------------------
    |
    | These values are deployment secrets/inputs. The production seeder fails
    | closed when any value is missing and never supplies a default identity or
    | password. After the first successful login, rotate the password and enable
    | the approved multi-factor authentication controls.
    |
    */
    'admin' => [
        'name' => env('PRODUCTION_ADMIN_NAME'),
        'username' => env('PRODUCTION_ADMIN_USERNAME'),
        'email' => env('PRODUCTION_ADMIN_EMAIL'),
        'password' => env('PRODUCTION_ADMIN_PASSWORD'),
    ],
];
