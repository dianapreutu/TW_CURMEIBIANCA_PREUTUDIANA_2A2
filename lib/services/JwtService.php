<?php

// lib/services/JwtService.php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private string $secret;
    private string $algo = 'HS256';
    private int $ttl = 3600; // 1 ora

    public function __construct()
    {
        $this->secret = JWT_SECRET;
    }

    public function generate(int $userId, string $username, string $role): string
    {
        $now = time();
        return JWT::encode([
            'iss'      => 'DoGen',
            'iat'      => $now,
            'exp'      => $now + $this->ttl,
            'user_id'  => $userId,
            'username' => $username,
            'role'     => $role,
        ], $this->secret, $this->algo);
    }

    public function validate(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key($this->secret, $this->algo));
        } catch (Exception $e) {
            return null;
        }
    }

    public function getFromRequest(): ?string
    {
        // Din cookie
        if (!empty($_COOKIE['jwt_token'])) {
            return $_COOKIE['jwt_token'];
        }
        // Din header Authorization: Bearer <token>
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    public function setcookie(string $token): void
    {
        setcookie('jwt_token', $token, [
            'expires'  => time() + $this->ttl,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    public function clearCookie(): void
    {
        setcookie('jwt_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
}