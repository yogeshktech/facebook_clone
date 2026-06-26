<?php

namespace App\Services;

use App\Mail\RegistrationOtpMail;
use App\Models\RegistrationOtp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    public function generateAndSend(string $email, string $name): void
    {
        $otp = (string) random_int(100000, 999999);

        RegistrationOtp::where('email', $email)->delete();

        RegistrationOtp::create([
            'email' => $email,
            'otp' => Hash::make($otp),
            'expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($email)->send(new RegistrationOtpMail($otp, $name));
    }

    public function verify(string $email, string $otp): bool
    {
        $record = RegistrationOtp::where('email', $email)
            ->where('verified', false)
            ->latest()
            ->first();

        if (! $record || $record->isExpired()) {
            return false;
        }

        if (! Hash::check($otp, $record->otp)) {
            return false;
        }

        $record->update(['verified' => true]);

        return true;
    }
}
