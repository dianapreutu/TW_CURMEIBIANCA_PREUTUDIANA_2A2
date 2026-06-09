<?php

// lib/services/AuthService.php
// Autentificare bazata exclusiv pe JWT — fara $_SESSION

class AuthService
{
    private ?object $payload = null;
    private JwtService $jwt;

    public function __construct()
    {
        $this->jwt = new JwtService();
        $token = $this->jwt->getFromRequest();
        if ($token) {
            $this->payload = $this->jwt->validate($token);
        }
    }

    public function isAdmin(): bool
    {
        return $this->payload && $this->payload->role === 'admin';
    }

    public function getUserId(): ?int
    {
        return $this->payload ? (int)$this->payload->user_id : null;
    }

    public function getUsername(): string
    {
        return $this->payload->username ?? 'Admin';
    }

    public function getEffectiveUserId(): ?int
    {
        $id = $this->getUserId();
        return $id ?: ($this->isAdmin() ? 1 : null);
    }

    public function requireAuthentication(): void
    {
        if ($this->getEffectiveUserId() === null) {
            http_response_code(401);
            throw new Exception('Trebuie sa fii autentificat pentru a accesa aceasta resursa.');
        }
    }

    public function isAuthenticated(): bool
    {
        return $this->getEffectiveUserId() !== null;
    }

    public function getJwt(): JwtService
    {
        return $this->jwt;
    }
}