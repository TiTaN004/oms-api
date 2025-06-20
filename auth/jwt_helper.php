<?php
// jwt_helper.php

require_once '../vendor/autoload.php'; // adjust path as needed

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTHelper {
    private static $secret = 'infix6@2174key';
    private static $algo = 'HS256';

    public static function encode(array $payload, int $expirySeconds = 3600): string {
        $issuedAt = time();
        $expireAt = $issuedAt + $expirySeconds;

        $payload['iat'] = $issuedAt;
        $payload['exp'] = $expireAt;

        return JWT::encode($payload, self::$secret, self::$algo);
    }

    public static function decode(string $token): array|false {
        try {
            $decoded = JWT::decode($token, new Key(self::$secret, self::$algo));
            return (array)$decoded;
        } catch (\Exception $e) {
            // Optionally log: $e->getMessage()
            return false;
        }
    }

    public static function verify(string $token): bool {
        return self::decode($token) !== false;
    }
}
