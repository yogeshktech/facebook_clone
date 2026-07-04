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

    /** Minutes after send during which the sender may edit a message. */
    'edit_window_minutes' => (int) env('CHAT_EDIT_WINDOW_MINUTES', 15),

    /** Minutes after send during which the sender may delete for everyone. */
    'delete_for_everyone_minutes' => (int) env('CHAT_DELETE_FOR_EVERYONE_MINUTES', 60),

];

