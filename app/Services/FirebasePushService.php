<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\SocialNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebasePushService
{
    public function isConfigured(): bool
    {
        return filled(config('services.firebase.server_key'))
            || filled(config('services.firebase.credentials_path'));
    }

    public function sendToUser(SocialNotification $notification): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $tokens = DeviceToken::where('user_id', $notification->receiver_id)->pluck('token');

        foreach ($tokens as $token) {
            $this->sendToToken($token, $notification);
        }
    }

    public function sendToToken(string $token, SocialNotification $notification): void
    {
        $serverKey = config('services.firebase.server_key');

        if ($serverKey) {
            $this->sendLegacyFcm($token, $notification, $serverKey);

            return;
        }

        $this->sendHttpV1($token, $notification);
    }

    private function sendLegacyFcm(string $token, SocialNotification $notification, string $serverKey): void
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'key='.$serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $token,
                'notification' => [
                    'title' => $notification->title,
                    'body' => $notification->message,
                    'icon' => asset('favicon.svg'),
                ],
                'data' => [
                    'url' => $notification->url ?? '',
                    'type' => $notification->type,
                    'reference_id' => (string) ($notification->reference_id ?? ''),
                ],
            ]);

            if ($response->failed()) {
                Log::warning('FCM legacy push failed', ['body' => $response->body()]);
            }
        } catch (\Throwable $e) {
            Log::warning('FCM legacy push error: '.$e->getMessage());
        }
    }

    private function sendHttpV1(string $token, SocialNotification $notification): void
    {
        $credentialsPath = config('services.firebase.credentials_path');

        if (! $credentialsPath || ! is_file($credentialsPath)) {
            return;
        }

        try {
            $credentials = json_decode(file_get_contents($credentialsPath), true);
            $projectId = $credentials['project_id'] ?? null;
            $accessToken = $this->getAccessToken($credentials);

            if (! $projectId || ! $accessToken) {
                return;
            }

            Http::withToken($accessToken)->post(
                "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $notification->title,
                            'body' => $notification->message,
                        ],
                        'data' => [
                            'url' => $notification->url ?? '',
                            'type' => $notification->type,
                            'reference_id' => (string) ($notification->reference_id ?? ''),
                        ],
                        'webpush' => [
                            'fcm_options' => [
                                'link' => $notification->url ?? config('app.url'),
                            ],
                        ],
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('FCM HTTP v1 push error: '.$e->getMessage());
        }
    }

    private function getAccessToken(array $credentials): ?string
    {
        $now = time();
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $claim = rtrim(strtr(base64_encode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ])), '+/', '-_'), '=');

        $privateKey = openssl_pkey_get_private($credentials['private_key']);
        if (! $privateKey) {
            return null;
        }

        openssl_sign("{$header}.{$claim}", $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $jwt = "{$header}.{$claim}.".rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        return $response->json('access_token');
    }
}
