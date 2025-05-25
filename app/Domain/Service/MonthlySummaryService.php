<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;

class MonthlySummaryService
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
    ) {}

    public function computeTotalExpenditure(User $user, int $year, int $month): float
    {
        $criteria = [
            'user_id' => $user->id,
            'year' => $year,
            'month' => $month,
        ];

        return $this->expenses->sumAmounts($criteria);
    }

    public function computePerCategoryTotals(User $user, int $year, int $month): array
    {
        $criteria = [
            'user_id' => $user->id,
            'year' => $year,
            'month' => $month,
        ];

        $totals = $this->expenses->sumAmountsByCategory($criteria);

        // Calculate percentages for visualization
        $totalAmount = array_sum($totals);
        $result = [];

        foreach ($totals as $category => $amount) {
            $percentage = $totalAmount > 0 ? ($amount / $totalAmount) * 100 : 0;
            $result[$category] = [
                'value' => $amount,
                'percentage' => round($percentage, 1),
            ];
        }

        // Sort by amount descending
        uasort($result, fn($a, $b) => $b['value'] <=> $a['value']);

        return $result;
    }

    public function computePerCategoryAverages(User $user, int $year, int $month): array
    {
        $criteria = [
            'user_id' => $user->id,
            'year' => $year,
            'month' => $month,
        ];

        $averages = $this->expenses->averageAmountsByCategory($criteria);

        // Calculate percentages for visualization
        $maxAverage = !empty($averages) ? max($averages) : 0;
        $result = [];

        foreach ($averages as $category => $amount) {
            $percentage = $maxAverage > 0 ? ($amount / $maxAverage) * 100 : 0;
            $result[$category] = [
                'value' => $amount,
                'percentage' => round($percentage, 1),
            ];
        }

        // Sort by amount descending
        uasort($result, fn($a, $b) => $b['value'] <=> $a['value']);

        return $result;
    }
}
