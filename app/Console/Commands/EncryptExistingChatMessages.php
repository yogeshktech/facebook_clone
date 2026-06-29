<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Support\ChatEncryption;
use Illuminate\Console\Command;

class EncryptExistingChatMessages extends Command
{
    protected $signature = 'chat:encrypt-existing {--dry-run : Show count without saving}';

    protected $description = 'Encrypt existing plaintext chat messages in the database';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $encrypted = 0;
        $skipped = 0;

        Message::query()->orderBy('id')->chunkById(100, function ($messages) use ($dryRun, &$encrypted, &$skipped) {
            foreach ($messages as $message) {
                $raw = $message->getAttributes()['body'] ?? '';

                if ($raw === '' || ChatEncryption::isEncrypted($raw)) {
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $encrypted++;

                    continue;
                }

                $message->forceFill([
                    'body' => ChatEncryption::encrypt($raw),
                ])->saveQuietly();

                $encrypted++;
            }
        });

        if ($dryRun) {
            $this->info("Would encrypt {$encrypted} message(s). Skipped {$skipped}.");
        } else {
            $this->info("Encrypted {$encrypted} message(s). Skipped {$skipped}.");
        }

        return self::SUCCESS;
    }
}
