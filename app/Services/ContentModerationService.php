<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContentModerationService
{
    /**
     * Check if the uploaded file contains adult/NSFW content.
     */
    public static function isAdult(UploadedFile $file): bool
    {
        $apiUser = config('services.sightengine.api_user');
        $apiSecret = config('services.sightengine.api_secret');

        // If credentials are not set, allow upload by default.
        if (empty($apiUser) || empty($apiSecret)) {
            return false;
        }

        $mime = $file->getMimeType();
        $isImage = str_starts_with($mime, 'image/');
        $isVideo = str_starts_with($mime, 'video/');

        if (!$isImage && !$isVideo) {
            return false;
        }

        try {
            $endpoint = $isImage 
                ? 'https://api.sightengine.com/1.0/check.json'
                : 'https://api.sightengine.com/1.0/video/check-sync.json';

            // Send file to Sightengine API
            $response = Http::asMultipart()
                ->attach('media', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
                ->post($endpoint, [
                    'api_user' => $apiUser,
                    'api_secret' => $apiSecret,
                    'models' => 'nudity-2.1,gore-2.0',
                ]);

            if (!$response->successful()) {
                Log::warning('Sightengine moderation API call failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return false;
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'success') {
                Log::warning('Sightengine returned error status', ['response' => $data]);
                return false;
            }

            // Load custom thresholds from config
            $thresholdRaw = config('moderation.thresholds.nudity_raw', 0.50);
            $thresholdPartial = config('moderation.thresholds.nudity_partial', 0.70);
            $thresholdGore = config('moderation.thresholds.gore', 0.50);

            // Check nudity results
            if (isset($data['nudity'])) {
                $rawScore = $data['nudity']['raw'] ?? 0;
                $partialScore = $data['nudity']['partial'] ?? 0;

                // Threshold of Raw nudity (explicit adult content)
                if ($rawScore > $thresholdRaw) {
                    Log::warning('Moderation: Raw nudity detected', ['raw_score' => $rawScore]);
                    return true;
                }

                // If partial nudity is extremely high, flag as adult
                if ($partialScore > $thresholdPartial) {
                    Log::warning('Moderation: Partial nudity detected', ['partial_score' => $partialScore]);
                    return true;
                }
            }

            // Check violence/gore results
            if (isset($data['gore'])) {
                $prob = $data['gore']['prob'] ?? 0;
                if ($prob > $thresholdGore) {
                    Log::warning('Moderation: Violence/Gore detected', ['gore_score' => $prob]);
                    return true;
                }
            }

        } catch (\Throwable $e) {
            Log::error('Content moderation service error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return false;
    }

    /**
     * Check if text contains profane/bad words.
     */
    public static function isProfane(?string $text): bool
    {
        if (empty($text)) {
            return false;
        }

        $badWords = config('moderation.bad_words', []);
        $lowerText = strtolower($text);

        foreach ($badWords as $word) {
            // Match word boundaries to prevent false positives (e.g. matching "assessment" for "ass")
            $pattern = '/\b' . preg_quote(strtolower($word), '/') . '\b/i';
            if (preg_match($pattern, $lowerText)) {
                Log::warning('Moderation: Profane content matched', ['matched_word' => $word]);
                return true;
            }
        }

        return false;
    }
}
