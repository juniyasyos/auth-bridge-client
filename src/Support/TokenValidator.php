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

        // Optional issuer check with normalization (localhost/127.0.0.1 equivalence)
        $expectedIss = config('iam.issuer');
        if ($expectedIss) {
            $tokenIss = property_exists($decoded, 'iss') ? (string) $decoded->iss : null;
            if (!$tokenIss || !self::isValidIssuer($tokenIss, $expectedIss)) {
                throw new \UnexpectedValueException('Invalid token issuer.');
            }
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

    /**
     * Check if token issuer is valid with flexible issuer comparison.
     * Treats localhost and 127.0.0.1 as equivalent for development/testing.
     */
    private static function isValidIssuer(string $tokenIss, string $expectedIss): bool
    {
        // Direct match
        if ($tokenIss === $expectedIss) {
            return true;
        }

        // Normalize localhost/127.0.0.1 for comparison
        $normalizedToken = self::normalizeIssuer($tokenIss);
        $normalizedExpected = self::normalizeIssuer($expectedIss);
        return $normalizedToken === $normalizedExpected;
    }

    /**
     * Normalize issuer for comparison (localhost ↔ 127.0.0.1).
     */
    private static function normalizeIssuer(string $issuer): string
    {
        return str_replace(
            ['http://localhost:', 'https://localhost:'],
            ['http://127.0.0.1:', 'https://127.0.0.1:'],
            $issuer
        );
    }
}
