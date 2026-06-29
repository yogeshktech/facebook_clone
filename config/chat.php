<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chat message encryption key
    |--------------------------------------------------------------------------
    |
    | Used to encrypt message bodies and chat media at rest in storage.
    | Generate: php artisan key:generate --show  (use a separate key from APP_KEY)
    |
    */

    'encryption_key' => env('CHAT_ENCRYPTION_KEY', env('APP_KEY')),

    'encrypted_prefix' => 'nbenc:',

];
