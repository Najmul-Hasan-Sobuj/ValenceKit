<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin URL Alias
    |--------------------------------------------------------------------------
    |
    | This value defines the URL prefix for all admin panel routes.
    | You can change the ADMIN_PATH environment variable to relocate the
    | entire admin panel to a custom path (e.g. "manage" → /manage/login).
    |
    */

    'alias' => env('ADMIN_PATH', 'admin'),
    'bootstrap_email' => env('ADMIN_BOOTSTRAP_EMAIL'),
    'bootstrap_password' => env('ADMIN_BOOTSTRAP_PASSWORD'),

];
