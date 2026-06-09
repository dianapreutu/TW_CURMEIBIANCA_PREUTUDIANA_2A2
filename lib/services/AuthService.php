<?php

// lib/services/AuthService.php
// Autentificare bazata exclusiv pe JWT — fara $_SESSION

class AuthService
{
    // Payload-ul decodat din JWT (daca tokenul este valid)
    private ?object $payload = null;

    // Serviciul responsabil cu generarea si validarea JWT
    private JwtService $jwt;

    public function __construct()
    {
        $this->jwt = new JwtService();

        // Preluam tokenul din request (cookie / header)
        $token = $this->jwt->getFromRequest();

        // Daca exista token, incercam validarea lui
        if ($token) {
            $this->payload = $this->jwt->validate($token);
        }
    }

    // Verifica daca utilizatorul curent are rol de admin
    public function isAdmin(): bool
    {
        return $this->payload && $this->payload->role === 'admin';
    }

    // Returneaza ID-ul utilizatorului din token (daca exista)
    public function getUserId(): ?int
    {
        return $this->payload ? (int)$this->payload->user_id : null;
    }

    // Returneaza username-ul din token sau valoare default
    public function getUsername(): string
    {
        return $this->payload->username ?? 'Admin';
    }

    // Returneaza ID-ul efectiv folosit in aplicatie
    // Adminul este tratat implicit cu ID = 1 daca nu exista payload
    public function getEffectiveUserId(): ?int
    {
        $id = $this->getUserId();

        return $id ?: ($this->isAdmin() ? 1 : null);
    }

    // Forteaza autentificarea; opreste executia daca nu exista utilizator valid
    public function requireAuthentication(): void
    {
        if ($this->getEffectiveUserId() === null) {
            http_response_code(401);
            throw new Exception('Trebuie sa fii autentificat pentru a accesa aceasta resursa.');
        }
    }

    // Verifica daca exista un utilizator autentificat (valid sau admin fallback)
    public function isAuthenticated(): bool
    {
        return $this->getEffectiveUserId() !== null;
    }

    // Returneaza instanta JwtService pentru operatii directe (generate, validate, etc.)
    public function getJwt(): JwtService
    {
        return $this->jwt;
    }
}