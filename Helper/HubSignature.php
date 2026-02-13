<?php

namespace Kanboard\Plugin\GithubWebhook\Helper;

/**
 * GitHub X-Hub-Signature-256 verification (HMAC-SHA256).
 */
class HubSignature
{
    /**
     * Verify GitHub X-Hub-Signature-256 header.
     *
     * @param  string $rawBody Raw request body
     * @param  string $header  X-Hub-Signature-256 value (e.g. "sha256=hex...")
     * @param  string $secret  Webhook secret
     * @return bool
     */
    public static function verify($rawBody, $header, $secret)
    {
        if ($secret === '' || $header === '' || strpos($header, 'sha256=') !== 0) {
            return false;
        }
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $header);
    }
}
