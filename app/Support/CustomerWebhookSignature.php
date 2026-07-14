<?php

namespace App\Support;

class CustomerWebhookSignature
{
    public static function sign(string $secret, int $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }
}
