<?php

namespace App\Contracts;

interface EmailSenderInterface
{
    public function send(string $to, string $subject, string $body): void;
}
