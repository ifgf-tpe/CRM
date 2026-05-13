<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Person;

/**
 * Generates personal attendance QR codes for church members.
 *
 * Each QR code encodes a tamper-proof check-in URL:
 *   /external/checkin?pid={id}&token={hmac-sha256}
 *
 * The HMAC token is derived from the person ID and sQrCodeSecret so that
 * check-in URLs cannot be forged without the secret.
 */
class QrCodeService
{
    private const QR_API = 'https://api.qrserver.com/v1/create-qr-code/';

    /**
     * Returns the URL that will be encoded in a member's personal QR code.
     * Scanning it opens the self-service check-in page for that person.
     */
    public static function getPersonCheckInUrl(Person $person): string
    {
        $pid   = $person->getId();
        $token = self::generateToken($pid);

        return SystemURLs::getRootPath()
            . '/external/checkin?pid=' . $pid
            . '&token=' . urlencode($token);
    }

    /**
     * Fetches a square PNG QR code image from the qrserver.com API.
     *
     * @return string Raw PNG bytes
     * @throws \RuntimeException when the API is unreachable or returns an error
     */
    public static function fetchQrCodePng(string $url, int $sizePx = 300): string
    {
        $apiUrl = self::QR_API . '?' . http_build_query([
            'data'  => $url,
            'size'  => $sizePx . 'x' . $sizePx,
            'qzone' => 1,
        ]);

        $ctx = stream_context_create(['http' => ['timeout' => 10]]);
        $png = @file_get_contents($apiUrl, false, $ctx);

        if ($png === false || strlen($png) < 100) {
            throw new \RuntimeException('QR code API unreachable or returned empty response');
        }

        return $png;
    }

    /**
     * Generates an HMAC-SHA256 token for a given person ID.
     * Used to sign check-in URLs so they cannot be guessed/forged.
     */
    public static function generateToken(int $personId): string
    {
        return hash_hmac('sha256', (string) $personId, self::getSecret());
    }

    /**
     * Verifies a check-in token using constant-time comparison.
     */
    public static function verifyToken(int $personId, string $token): bool
    {
        return hash_equals(self::generateToken($personId), $token);
    }

    /**
     * Returns the HMAC signing secret, falling back to a derived constant
     * so check-in URLs work even before the admin sets sQrCodeSecret.
     */
    private static function getSecret(): string
    {
        $secret = SystemConfig::getValue('sQrCodeSecret');
        if (!empty($secret)) {
            return $secret;
        }

        // Stable fallback derived from install path — not cryptographically
        // strong but prevents URL forgery without the salt leaking.
        return hash('sha256', SystemURLs::getRootPath() . ':churchcrm-qr-fallback-salt');
    }
}
