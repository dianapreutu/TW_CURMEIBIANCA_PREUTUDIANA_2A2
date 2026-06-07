<?php

// lib/services/AuthService.php
// Serviciu pentru autentificare si gestionarea sesiunii curente
// Expune starea utilizatorului autentificat, inclusiv modul admin

class AuthService
{
    // Verifica daca utilizatorul curent are rol de administrator
    public function isAdmin(): bool
    {
        return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
    }

    // Returneaza ID-ul utilizatorului din sesiune sau null daca nu e autentificat
    public function getUserId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    // Returneaza ID-ul efectiv al utilizatorului
    // Adminul fara cont explicit primeste ID-ul 1
    public function getEffectiveUserId(): ?int
    {
        $userId = $this->getUserId();
        return $userId ?: ($this->isAdmin() ? 1 : null);
    }

    // Arunca exceptie daca utilizatorul nu este autentificat
    public function requireAuthentication(): void
    {
        if ($this->getEffectiveUserId() === null) {
            throw new Exception('Trebuie sa fii autentificat pentru a accesa aceasta resursa.');
        }
    }
}