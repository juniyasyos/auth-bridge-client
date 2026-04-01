<?php

namespace Juniyasyos\IamClient\Support;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class TokenExpiryManager
{
    /**
     * Extract expiry information dari JWT token.
     * 
     * @param string $token JWT token
     * @return array{exp: int, exp_at: string, remaining_seconds: int, remaining_minutes: int}|null
     */
    public static function extractExpiry(string $token): ?array
    {
        try {
            $secret = config('iam.jwt_secret');
            $algo = config('iam.jwt_algorithm', 'HS256');
            $leeway = (int) config('iam.jwt_leeway', 0);

            if (empty($secret)) {
                return null;
            }

            if ($leeway > 0) {
                JWT::$leeway = $leeway;
            }

            $decoded = JWT::decode($token, new Key($secret, $algo));

            if (!isset($decoded->exp)) {
                return null;
            }

            $now = time();
            $exp = (int) $decoded->exp;
            $remainingSeconds = max(0, $exp - $now);
            $remainingMinutes = (int) ceil($remainingSeconds / 60);

            return [
                'exp' => $exp,
                'exp_at' => \Carbon\Carbon::createFromTimestamp($exp)->toIso8601String(),
                'remaining_seconds' => $remainingSeconds,
                'remaining_minutes' => $remainingMinutes,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Calculate session lifetime (in minutes) based on token expiry.
     * 
     * @param string $token JWT token
     * @param int $bufferMinutes Optional buffer to reduce from token TTL
     * @return int|null Session lifetime in minutes, or null if cannot determine
     */
    public static function calculateSessionLifetime(string $token, int $bufferMinutes = 2): ?int
    {
        $expiry = self::extractExpiry($token);

        if (!$expiry) {
            return null;
        }

        // Session lifetime should be token TTL minus buffer
        $sessionLifetime = max(1, $expiry['remaining_minutes'] - $bufferMinutes);

        return $sessionLifetime;
    }

    /**
     * Check if token is approaching expiry (within threshold).
     * 
     * @param string $token JWT token
     * @param int $thresholdMinutes Minutes before expiry to consider as "approaching"
     * @return bool
     */
    public static function isApproachingExpiry(string $token, int $thresholdMinutes = 5): bool
    {
        $expiry = self::extractExpiry($token);

        if (!$expiry) {
            return false;
        }

        return $expiry['remaining_minutes'] <= $thresholdMinutes;
    }

    /**
     * Get human-readable remaining time.
     * 
     * @param string $token JWT token
     * @return string
     */
    public static function getRemainingTimeString(string $token): string
    {
        $expiry = self::extractExpiry($token);

        if (!$expiry) {
            return 'Unknown';
        }

        if ($expiry['remaining_seconds'] <= 0) {
            return 'Expired';
        }

        if ($expiry['remaining_minutes'] >= 60) {
            $hours = (int) floor($expiry['remaining_minutes'] / 60);
            $mins = $expiry['remaining_minutes'] % 60;
            return "{$hours}h {$mins}m";
        }

        return "{$expiry['remaining_minutes']}m";
    }
}
