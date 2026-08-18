<?php

namespace App\Services\Webhook;

use Illuminate\Support\Facades\Hash;

class HmacSignatureService
{
    const ALGORITHM = 'sha256';

    public function generateSignature($payload, $secret)
    {
        if (is_array($payload)) {
            $payload = json_encode($payload);
        }

        return hash_hmac(self::ALGORITHM, $payload, $secret);
    }

    public function verifySignature($payload, $signature, $secret)
    {
        if (is_array($payload)) {
            $payload = json_encode($payload);
        }

        $expectedSignature = $this->generateSignature($payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    public function generateWebhookSecret()
    {
        return bin2hex(random_bytes(32));
    }

    public function isValidSignatureFormat($signature)
    {
        return preg_match('/^[a-f0-9]{64}$/i', $signature) === 1;
    }
}
