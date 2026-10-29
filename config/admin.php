<?php

return [

    /*
    |--------------------------------------------------------------------------
    | First administrator
    |--------------------------------------------------------------------------
    |
    | Read by RolesAndPermissionsSeeder. Set ADMIN_EMAIL, ADMIN_PASSWORD and
    | ADMIN_NAME in .env; nothing is hard-coded.
    |
    */

    'name' => env('ADMIN_NAME', 'Administrator'),

    'email' => env('ADMIN_EMAIL'),

    'password' => env('ADMIN_PASSWORD'),

];
