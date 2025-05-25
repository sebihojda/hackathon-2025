<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function register(string $username, string $password): User
    {
        // TODO: check that a user with same username does not exist, create new user and persist
        // TODO: make sure password is not stored in plain, and proper PHP functions are used for that

        // TODO: here is a sample code to start with
        /*$user = new User(null, $username, $password, new \DateTimeImmutable());
        $this->users->save($user);

        return $user;*/

        // Check if user already exists
        $existingUser = $this->users->findByUsername($username);
        if ($existingUser !== null) {
            throw new \InvalidArgumentException('Username already exists');
        }

        // Validate username (≥ 4 chars)
        if (strlen($username) < 4) {
            throw new \InvalidArgumentException('Username must be at least 4 characters long');
        }

        // Validate password (≥ 8 chars, 1 number)
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters long');
        }

        if (!preg_match('/\d/', $password)) {
            throw new \InvalidArgumentException('Password must contain at least one number');
        }

        // Hash password properly
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $user = new User(null, $username, $hashedPassword, new \DateTimeImmutable());
        $this->users->save($user);

        return $user;
    }

    public function attempt(string $username, string $password): bool
    {
        // TODO: implement this for authenticating the user
        // TODO: make sure the user exists and the password matches
        // TODO: don't forget to store in session user data needed afterwards

        /*return true;*/

        $user = $this->users->findByUsername($username);

        if ($user === null) {
            return false;
        }

        if (!password_verify($password, $user->passwordHash)) {
            return false;
        }

        // Start session and store user data
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $user->username;

        return true;
    }
}
