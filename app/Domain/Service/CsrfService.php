<?php

declare(strict_types=1);

namespace App\Domain\Service;

class CsrfService
{
    private const TOKEN_LENGTH = 32;
    private const SESSION_KEY = 'csrf_tokens';

    public function generateToken(string $formName = 'default'): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));

        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        $_SESSION[self::SESSION_KEY][$formName] = $token;

        return $token;
    }

    public function validateToken(string $token, string $formName = 'default'): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[self::SESSION_KEY][$formName])) {
            return false;
        }

        $storedToken = $_SESSION[self::SESSION_KEY][$formName];

        // Remove token after validation (one-time use)
        unset($_SESSION[self::SESSION_KEY][$formName]);

        return hash_equals($storedToken, $token);
    }

    public function getTokenField(string $formName = 'default'): string
    {
        $token = $this->generateToken($formName);
        return sprintf('<input type="hidden" name="csrf_token" value="%s">', htmlspecialchars($token));
    }

}