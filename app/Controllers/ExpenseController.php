<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Service\ExpenseService;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class ExpenseController extends BaseController
{
    private const PAGE_SIZE = 20;

    public function __construct(
        Twig $view,
        private readonly ExpenseService $expenseService,
        private readonly UserRepositoryInterface $userRepository,
    ) {
        parent::__construct($view);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function index(Request $request, Response $response): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['user_id'];
        $queryParams = $request->getQueryParams();

        $year = (int)($queryParams['year'] ?? date('Y'));
        $month = (int)($queryParams['month'] ?? date('n'));
        $page = (int)($queryParams['page'] ?? 1);
        $pageSize = (int)($queryParams['pageSize'] ?? self::PAGE_SIZE);

        $result = $this->expenseService->list($userId, $year, $month, $page, $pageSize);

        $years = $this->expenseService->getYears($userId);

        // Get flash message if any
        $flashMessage = $_SESSION['flash_message'] ?? null;
        unset($_SESSION['flash_message']);

        return $this->render($response, 'expenses/index.twig', [
            'expenses' => $result['expenses'],
            'total' => $result['total'],
            'page' => $page,
            'pageSize' => $pageSize,
            'year' => $year,
            'month' => $month,
            'hasNext' => $result['hasNext'],
            'hasPrevious' => $result['hasPrevious'],
            'availableYears' => $years ? $years : [2025],
            'flashMessage' => $flashMessage
        ]);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function create(Request $request, Response $response): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $categories = $this->expenseService->getValidCategories();

        return $this->render($response, 'expenses/create.twig', [
            'categories' => $categories,
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $_SESSION['old'] ?? [],
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['user_id'];
        $data = $request->getParsedBody();

        try {
            $amount = (float) $data['amount'];
            $description = trim($data['description']);
            $date = new DateTimeImmutable($data['date']);
            $category = $data['category'];

            $this->expenseService->create($userId, $amount, $description, $date, $category);

            // Clear any previous form data
            unset($_SESSION['errors'], $_SESSION['old']);

            return $response->withHeader('Location', '/expenses')->withStatus(302);
        } catch (InvalidArgumentException $e) {
            $_SESSION['errors'] = [$e->getMessage()];
            $_SESSION['old'] = $data;

            return $response->withHeader('Location', '/expenses/create')->withStatus(302);
        }
    }

    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function edit(Request $request, Response $response, array $routeParams): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['user_id'];
        $expenseId = (int) $routeParams['id'];

        $expense = $this->expenseService->findById($expenseId);

        if (!$expense || $expense->userId !== $userId) {
            return $response->withStatus(403);
        }

        $categories = $this->expenseService->getValidCategories();

        return $this->render($response, 'expenses/edit.twig', [
            'expense' => $expense,
            'categories' => $categories,
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $_SESSION['old'] ?? [],
        ]);
    }

    public function update(Request $request, Response $response, array $routeParams): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['user_id'];
        $expenseId = (int) $routeParams['id'];
        $data = $request->getParsedBody();

        $expense = $this->expenseService->findById($expenseId);

        if (!$expense || $expense->userId !== $userId) {
            return $response->withStatus(403);
        }

        try {
            $amount = (float) $data['amount'];
            $description = trim($data['description']);
            $date = new DateTimeImmutable($data['date']);
            $category = $data['category'];

            $this->expenseService->update($expense, $amount, $description, $date, $category);

            // Clear any previous form data
            unset($_SESSION['errors'], $_SESSION['old']);

            return $response->withHeader('Location', '/expenses')->withStatus(302);
        } catch (InvalidArgumentException $e) {
            $_SESSION['errors'] = [$e->getMessage()];
            $_SESSION['old'] = $data;

            return $response->withHeader('Location', "/expenses/{$expenseId}/edit")->withStatus(302);
        }
    }

    public function destroy(Request $request, Response $response, array $routeParams): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['user_id'];
        $expenseId = (int) $routeParams['id'];

        $expense = $this->expenseService->findById($expenseId);

        if (!$expense || $expense->userId !== $userId) {
            return $response->withStatus(403);
        }

        $this->expenseService->delete($expenseId);

        $_SESSION['flash_message'] = [
            'type' => 'success',
            'message' => 'Expense deleted successfully!'
        ];

        return $response->withHeader('Location', '/expenses')->withStatus(302);
    }

    public function import(Request $request, Response $response): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['user_id'];
        $user = $this->userRepository->find($userId);

        if (!$user) {
            return $response->withStatus(403);
        }

        $uploadedFiles = $request->getUploadedFiles();
        $csvFile = $uploadedFiles['csv'] ?? null;

        if (!$csvFile || $csvFile->getError() !== UPLOAD_ERR_OK) {
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'message' => 'Please select a valid CSV file to upload.'
            ];
            return $response->withHeader('Location', '/expenses')->withStatus(302);
        }

        try {
            $importedCount = $this->expenseService->importFromCsv($user, $csvFile);

            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => "Successfully imported {$importedCount} expenses from CSV file!"
            ];
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'message' => 'CSV import failed: ' . $e->getMessage()
            ];
        }

        return $response->withHeader('Location', '/expenses')->withStatus(302);
    }
}
