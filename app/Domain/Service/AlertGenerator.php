<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;

class AlertGenerator
{
    // TODO: refactor the array below and make categories and their budgets configurable in .env
    // Hint: store them as JSON encoded in .env variable, inject them manually in a dedicated service,
    // then inject and use use that service wherever you need category/budgets information.
    /*private array $categoryBudgets = [
        'Groceries' => 300.00,
        'Utilities' => 200.00,
        'Transport' => 500.00,
        // ...
    ];*/

    /*private array $categoryBudgets = [
        'groceries' => 300.00,
        'utilities' => 200.00,
        'transport' => 500.00,
        'entertainment' => 150.00,
        'housing' => 500.00,
        'healthcare' => 100.00,
        'shopping' => 250.00,
        'dining' => 200.00,
        'education' => 100.00,
        'travel' => 400.00,
        'other' => 100.00,
    ];*/

    private array $categoryBudgets;

    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
    ) {
        $this->categoryBudgets = json_decode($_ENV['CATEGORIES_BUDGETS'], true);
    }

    public function generate(User $user, int $year, int $month): array
    {
        // TODO: implement this to generate alerts for overspending by category

        /*return [];*/

        $criteria = [
            'user_id' => $user->id,
            'year' => $year,
            'month' => $month,
        ];

        $categoryTotals = $this->expenses->sumAmountsByCategory($criteria);
        $alerts = [];

        foreach ($this->categoryBudgets as $category => $budget) {
            $spent = $categoryTotals[$category] ?? 0;

            if ($spent > $budget) {
                $overspent = $spent - $budget;
                $alerts[] = [
                    'type' => 'warning',
                    'category' => $category,
                    'budget' => $budget,
                    'spent' => $spent,
                    'overspent' => $overspent,
                    'message' => sprintf('⚠ %s budget exceeded by %.2f €', $category, $overspent),
                ];
            }
        }

        // If no overspending, add a success message
        if (empty($alerts)) {
            $alerts[] = [
                'type' => 'success',
                'message' => '✅ Looking good! You\'re within budget for this month.',
            ];
        }

        return $alerts;
    }

    public function getCategoryBudgets(): array
    {
        return $this->categoryBudgets;
    }

    public function getCategories(): array
    {
        return array_keys($this->categoryBudgets);
    }
}
