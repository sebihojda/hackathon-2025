<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\Expense;
use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;

class ExpenseService
{
    /*private const VALID_CATEGORIES = [
        'groceries',
        'utilities',
        'transport',
        'entertainment',
        'housing',
        'healthcare',
        'shopping',
        'dining',
        'education',
        'travel',
        'other',
    ];*/

    private array $categories;

    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
    ) {
        $this->categories = array_keys(json_decode($_ENV['CATEGORIES_BUDGETS'], true));
    }

    public function list(/*User $user,*/int $userId, int $year, int $month, int $pageNumber, int $pageSize): array
    {
        // TODO: implement this and call from controller to obtain paginated list of expenses
        /*return [];*/

        $criteria = [
            'user_id' => $userId,
            'year' => $year,
            'month' => $month,
        ];

        $offset = ($pageNumber - 1) * $pageSize;
        $expenses = $this->expenses->findBy($criteria, $offset, $pageSize);
        $total = $this->expenses->countBy($criteria);

        return [
            'expenses' => $expenses,
            'total' => $total,
            'hasNext' => ($offset + $pageSize) < $total,
            'hasPrevious' => $pageNumber > 1,
        ];
    }

    public function create(
        /*User $user,*/
        int $userId,
        float $amount,
        string $description,
        DateTimeImmutable $date,
        string $category,
    ): void {
        // TODO: implement this to create a new expense entity, perform validation, and persist

        // TODO: here is a code sample to start with
        /*$expense = new Expense(null, $user->id, $date, $category, (int)$amount, $description);
        $this->expenses->save($expense);*/

        $this->validateExpenseData($amount, $description, $date, $category);

        $amountCents = (int) round($amount * 100);
        $expense = new Expense(null, $userId, $date, $category, $amountCents, $description);
        $this->expenses->save($expense);
    }

    public function update(
        Expense $expense,
        float $amount,
        string $description,
        DateTimeImmutable $date,
        string $category,
    ): void {
        // TODO: implement this to update expense entity, perform validation, and persist

        $this->validateExpenseData($amount, $description, $date, $category);

        $expense->amountCents = (int) round($amount * 100);
        $expense->description = $description;
        $expense->date = $date;
        $expense->category = $category;

        $this->expenses->save($expense);
    }

    public function findById(int $id): ?Expense
    {
        return $this->expenses->find($id);
    }

    public function delete(int $id): void
    {
        $this->expenses->delete($id);
    }

    public function getYears(int $userId): array
    {
        return $this->expenses->listExpenditureYears($userId);
    }

    public function getValidCategories(): array
    {
        return $this->categories;
    }

    private function validateExpenseData(
        float $amount,
        string $description,
        DateTimeImmutable $date,
        string $category
    ): void {
        $errors = [];

        if ($amount <= 0) {
            $errors[] = 'Amount must be greater than 0';
        }

        if (empty(trim($description))) {
            $errors[] = 'Description cannot be empty';
        }

        if ($date > new DateTimeImmutable()) {
            $errors[] = 'Date cannot be in the future';
        }

        if (!in_array($category, $this->categories)) {
            $errors[] = 'Invalid category selected';
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(', ', $errors));
        }
    }

    public function importFromCsv(User $user, UploadedFileInterface $csvFile): int
    {
        // TODO: process rows in file stream, create and persist entities
        // TODO: for extra points wrap the whole import in a transaction and rollback only in case writing to DB fails

        return 0; // number of imported rows
    }
}
