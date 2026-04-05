<?php

namespace Juniyasyos\IamClient\Support;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class TokenValidator
{
    /**
     * Decode and validate JWT locally (signature + basic claims).
     *
     * @throws \UnexpectedValueException on invalid token
     */
    public static function decode(string $token): object
    {
        $secret = config('iam.jwt_secret');
        $algo = config('iam.jwt_algorithm', 'HS256');
        $leeway = (int) config('iam.jwt_leeway', 0);

        if (empty($secret)) {
            throw new \UnexpectedValueException('JWT secret not configured.');
        }

        // Decode base64-encoded secrets (Laravel convention: base64:xxxxx)
        if (is_string($secret) && str_starts_with($secret, 'base64:')) {
            $decoded = base64_decode(substr($secret, 7), true);
            if ($decoded === false) {
                throw new \UnexpectedValueException('Invalid base64-encoded JWT secret.');
            }
            $secret = $decoded;
        }

        if ($leeway > 0) {
            JWT::$leeway = $leeway;
        }

        $decoded = JWT::decode($token, new Key($secret, $algo));

        // Optional issuer check
        $expectedIss = config('iam.issuer');
        if ($expectedIss && (! property_exists($decoded, 'iss') || (string) $decoded->iss !== (string) $expectedIss)) {
            throw new \UnexpectedValueException('Invalid token issuer.');
        }

        // Optional audience check (fallback to app_key when audience not set)
        $expectedAud = config('iam.audience') ?? config('iam.app_key');
        if ($expectedAud && property_exists($decoded, 'aud')) {
            $aud = $decoded->aud;
            $matches = false;

            if (is_array($aud)) {
                $matches = in_array($expectedAud, $aud, true);
            } else {
                $matches = ((string) $aud === (string) $expectedAud);
            }

            if (! $matches) {
                throw new \UnexpectedValueException('Invalid token audience.');
            }
        }

        return $decoded;
    }
}
