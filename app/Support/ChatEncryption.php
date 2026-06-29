<?php

namespace App\Support;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;
use RuntimeException;

class ChatEncryption
{
    private static ?Encrypter $encrypter = null;

    public static function prefix(): string
    {
        return config('chat.encrypted_prefix', 'nbenc:');
    }

    public static function isEncrypted(?string $value): bool
    {
        return $value !== null && $value !== '' && str_starts_with($value, self::prefix());
    }

    public static function isEncryptedMedia(?string $path): bool
    {
        return $path !== null && Str::endsWith(strtolower($path), '.enc');
    }

    public static function encrypt(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (self::isEncrypted($value)) {
            return $value;
        }

        return self::prefix().self::encrypter()->encryptString($value);
    }

    public static function decrypt(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (! self::isEncrypted($value)) {
            return $value;
        }

        return self::encrypter()->decryptString(
            substr($value, strlen(self::prefix()))
        );
    }

    public static function encryptBytes(string $data): string
    {
        return self::encrypter()->encryptString(base64_encode($data));
    }

    public static function decryptBytes(string $encrypted): string
    {
        return base64_decode(self::encrypter()->decryptString($encrypted));
    }

    private static function encrypter(): Encrypter
    {
        if (self::$encrypter) {
            return self::$encrypter;
        }

        $key = config('chat.encryption_key');

        if (! $key) {
            throw new RuntimeException('CHAT_ENCRYPTION_KEY or APP_KEY must be set for chat encryption.');
        }

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true);
        }

        self::$encrypter = new Encrypter($key, config('app.cipher', 'AES-256-CBC'));

        return self::$encrypter;
    }
}
