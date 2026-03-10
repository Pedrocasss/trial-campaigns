<?php

namespace App\Services;

use App\Contracts\EmailSenderInterface;
use Illuminate\Support\Facades\Log;

class LogEmailSender implements EmailSenderInterface
{
    public function send(string $to, string $subject, string $body): void
    {
        Log::info("Sending email to {$to}: {$subject}");
    }
}
