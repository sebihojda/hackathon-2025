<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;

class AlertGenerator
{
    private array $categoryBudgets;

    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
    ) {
        $this->categoryBudgets = json_decode($_ENV['CATEGORIES_BUDGETS'], true);
    }

    public function generate(User $user, int $year, int $month): array
    {
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
