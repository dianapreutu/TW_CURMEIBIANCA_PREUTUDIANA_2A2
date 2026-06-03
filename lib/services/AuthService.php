<?php

// lib/services/AuthService.php - Serviciu pentru autentificare si gestionarea utilizatorului curent
// Acest serviciu expune starea sesiunii si ID-ul utilizatorului, inclusiv modul admin
// ==================================================

class AuthService
{
    public function isAdmin(): bool
    {
        return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
    }

    public function getUserId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public function getEffectiveUserId(): ?int
    {
        $userId = $this->getUserId();
        return $userId ?: ($this->isAdmin() ? 1 : null);
    }

    public function requireAuthentication(): void
    {
        if ($this->getEffectiveUserId() === null) {
            throw new Exception('Trebuie sa fii autentificat pentru a accesa aceasta resursa.');
        }
    }
}
