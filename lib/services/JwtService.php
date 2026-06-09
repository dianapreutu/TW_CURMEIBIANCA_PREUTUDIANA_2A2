<?php

// lib/services/JwtService.php
// Serviciu pentru generarea, validarea si gestionarea token-urilor JWT

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    // Cheia secreta folosita pentru semnarea token-urilor
    private string $secret;

    // Algoritmul de semnare JWT
    private string $algo = 'HS256';

    // Durata de viata a token-ului (1 ora)
    private int $ttl = 3600;

    public function __construct()
    {
        // Preluam cheia secreta din configuratie globala
        $this->secret = JWT_SECRET;
    }

    // Genereaza un token JWT pentru un utilizator autentificat
    public function generate(int $userId, string $username, string $role): string
    {
        $now = time();

        // Payload standard JWT + date aplicatie
        return JWT::encode([
            'iss'      => 'DoGen',     // issuer aplicatie
            'iat'      => $now,        // moment creare token
            'exp'      => $now + $this->ttl, // expirare token
            'user_id'  => $userId,
            'username' => $username,
            'role'     => $role,
        ], $this->secret, $this->algo);
    }

    // Valideaza un token JWT si returneaza payload-ul decodat
    public function validate(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key($this->secret, $this->algo));
        } catch (Exception $e) {
            // Token invalid sau expirat
            return null;
        }
    }

    // Preia token-ul JWT din request (cookie sau header Authorization)
    public function getFromRequest(): ?string
    {
        // 1. Incercam din cookie (metoda principala)
        if (!empty($_COOKIE['jwt_token'])) {
            return $_COOKIE['jwt_token'];
        }

        // 2. Incercam din header Authorization Bearer
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }

        // Niciun token gasit
        return null;
    }

    // Seteaza cookie-ul HTTP-only pentru autentificare
    public function setcookie(string $token): void
    {
        setcookie('jwt_token', $token, [
            'expires'  => time() + $this->ttl,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    // Sterge cookie-ul JWT (logout)
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