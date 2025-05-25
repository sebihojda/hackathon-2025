<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Service\AuthService;
use App\Domain\Service\CsrfService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class AuthController extends BaseController
{
    public function __construct(
        Twig                             $view,
        private readonly AuthService     $authService,
        private readonly LoggerInterface $logger,
        private CsrfService $csrfService,
    ) {
        parent::__construct($view);
    }

    public function showRegister(Request $request, Response $response): Response
    {
        // TODO: you also have a logger service that you can inject and use anywhere; file is var/app.log
        $this->logger->info('Register page requested');

        return $this->render($response, 'auth/register.twig', [
            'csrfToken' => $this->csrfService->generateToken('register'),
        ]);
    }

    public function register(Request $request, Response $response): Response
    {
        // TODO: call corresponding service to perform user registration

        /*return $response->withHeader('Location', '/login')->withStatus(302);*/

        /*$data = $request->getParsedBody();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        try {
            $this->authService->register($username, $password);
            $this->logger->info('User registered successfully', ['username' => $username]);
            return $response->withHeader('Location', '/login')->withStatus(302);
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Registration failed', ['username' => $username, 'error' => $e->getMessage()]);
            return $this->render($response, 'auth/register.twig', [
                'errors' => ['general' => $e->getMessage()],
                'username' => $username,
            ]);
        }*/

        $data = $request->getParsedBody();
        $csrfToken = $data['csrf_token'] ?? '';

        if (!$this->csrfService->validateToken($csrfToken, 'register')) {
            $this->logger->warning('CSRF token validation failed for registration');
            return $this->render($response, 'auth/register.twig', [
                'errors' => ['general' => 'Security token validation failed. Please try again.'],
                'csrfToken' => $this->csrfService->generateToken('register'),
            ]);
        }

        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $passwordConfirm = $data['password_confirm'] ?? '';

        $errors = [];

        // Validate username
        if (strlen($username) < 4) {
            $errors['username'] = 'Username must be at least 4 characters long';
        }

        // Validate password
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters long';
        } elseif (!preg_match('/\d/', $password)) {
            $errors['password'] = 'Password must contain at least one number';
        }

        // Validate password confirmation
        if ($password !== $passwordConfirm) {
            $errors['password_confirm'] = 'Passwords do not match';
        }

        if (!empty($errors)) {
            return $this->render($response, 'auth/register.twig', [
                'errors' => $errors,
                'username' => $username,
                'csrfToken' => $this->csrfService->generateToken('register'),
            ]);
        }

        try {
            $this->authService->register($username, $password);
            $this->logger->info('User registered successfully', ['username' => $username]);
            return $response->withHeader('Location', '/login')->withStatus(302);
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Registration failed', ['username' => $username, 'error' => $e->getMessage()]);
            return $this->render($response, 'auth/register.twig', [
                'errors' => ['general' => $e->getMessage()],
                'username' => $username,
                'csrfToken' => $this->csrfService->generateToken('register'),
            ]);
        }
    }

    public function showLogin(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/login.twig', [
            'csrfToken' => $this->csrfService->generateToken('login'),
        ]);
    }

    public function login(Request $request, Response $response): Response
    {
        // TODO: call corresponding service to perform user login, handle login failures

        /*return $response->withHeader('Location', '/')->withStatus(302);*/

        /*$data = $request->getParsedBody();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if ($this->authService->attempt($username, $password)) {
            $this->logger->info('User logged in successfully', ['username' => $username]);
            return $response->withHeader('Location', '/')->withStatus(302);
        } else {
            $this->logger->warning('Login failed', ['username' => $username]);
            return $this->render($response, 'auth/login.twig', [
                'errors' => ['general' => 'Invalid username or password'],
                'username' => $username,
            ]);
        }*/

        $data = $request->getParsedBody();
        $csrfToken = $data['csrf_token'] ?? '';

        if (!$this->csrfService->validateToken($csrfToken, 'login')) {
            $this->logger->warning('CSRF token validation failed for login');
            return $this->render($response, 'auth/login.twig', [
                'errors' => ['general' => 'Security token validation failed. Please try again.'],
                'csrfToken' => $this->csrfService->generateToken('login'),
            ]);
        }

        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        if ($this->authService->attempt($username, $password)) {
            // Prevent session fixation attacks
            session_regenerate_id(true);

            $this->logger->info('User logged in successfully', ['username' => $username]);
            return $response->withHeader('Location', '/')->withStatus(302);
        } else {
            $this->logger->warning('Login failed', ['username' => $username]);
            return $this->render($response, 'auth/login.twig', [
                'errors' => ['general' => 'Invalid username or password'],
                'username' => $username,
                'csrfToken' => $this->csrfService->generateToken('login'),
            ]);
        }
    }

    public function logout(Request $request, Response $response): Response
    {
        // TODO: handle logout by clearing session data and destroying session

        /*return $response->withHeader('Location', '/login')->withStatus(302);*/

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->logger->info('User logged out', ['user_id' => $_SESSION['user_id'] ?? null]);

        // Clear session data and destroy session
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();

        return $response->withHeader('Location', '/login')->withStatus(302);
    }
}
