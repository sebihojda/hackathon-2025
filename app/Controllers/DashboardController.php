<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Service\AlertGenerator;
use App\Domain\Service\MonthlySummaryService;
use App\Domain\Repository\ExpenseRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class DashboardController extends BaseController
{
    public function __construct(
        Twig $view,
        private readonly MonthlySummaryService $monthlySummaryService,
        private readonly AlertGenerator $alertGenerator,
        private readonly ExpenseRepositoryInterface $expenseRepository,
        private readonly UserRepositoryInterface $userRepository,
    )
    {
        parent::__construct($view);
    }

    /**
     * @throws \Exception
     */
    public function index(Request $request, Response $response): Response
    {
        // Get current user from session
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        // Create user object
        $user = $this->userRepository->find($userId);

        // Parse request parameters for year/month selection
        $queryParams = $request->getQueryParams();
        $selectedYear = (int) ($queryParams['year'] ?? date('Y'));
        $selectedMonth = (int) ($queryParams['month'] ?? date('n'));

        // Get available years for the year selector
        $availableYears = $this->expenseRepository->listExpenditureYears($user->id);

        // Generate overspending alerts for current month
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');
        $alerts = $this->alertGenerator->generate($user, $currentYear, $currentMonth);

        // Compute monthly summaries for selected year/month
        $totalForMonth = $this->monthlySummaryService->computeTotalExpenditure($user, $selectedYear, $selectedMonth);
        $totalsForCategories = $this->monthlySummaryService->computePerCategoryTotals($user, $selectedYear, $selectedMonth);
        $averagesForCategories = $this->monthlySummaryService->computePerCategoryAverages($user, $selectedYear, $selectedMonth);

        return $this->render($response, 'dashboard.twig', [
            'alerts' => $alerts,
            'totalForMonth' => $totalForMonth,
            'totalsForCategories' => $totalsForCategories,
            'averagesForCategories' => $averagesForCategories,
            'selectedYear' => $selectedYear,
            'selectedMonth' => $selectedMonth,
            'availableYears' => $availableYears,
        ]);
    }
}
