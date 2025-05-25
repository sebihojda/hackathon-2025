<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\Expense;
use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;
use Exception;

class ExpenseService
{
    private array $categories;

    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
        private readonly LoggerInterface $logger,
    ) {
        $this->categories = array_keys(json_decode($_ENV['CATEGORIES_BUDGETS'], true));
    }

    public function list(/*User $user,*/int $userId, int $year, int $month, int $pageNumber, int $pageSize): array
    {
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
        if ($csvFile->getError() !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('File upload error');
        }

        $stream = $csvFile->getStream();
        $content = $stream->getContents();
        $lines = explode("\n", $content);

        $importedCount = 0;
        $skippedCount = 0;
        $skippedReasons = [];
        $processedRows = [];

        // Start transaction for atomicity
        $this->expenses->beginTransaction();

        try {
            foreach ($lines as $lineNumber => $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                $columns = str_getcsv($line);
                if (count($columns) !== 4) {
                    $skippedCount++;
                    $skippedReasons[] = "Line " . ($lineNumber + 1) . ": Invalid number of columns";
                    continue;
                }

                [$dateStr, $amountStr, $description, $category] = $columns;

                // Validate and parse data
                try {
                    $date = new DateTimeImmutable($dateStr);
                    $amount = (float) $amountStr;
                    $description = trim($description);
                    $category = strtolower(trim($category));

                    // Check for valid category
                    if (!in_array($category, $this->categories)) {
                        $skippedCount++;
                        $skippedReasons[] = "Line " . ($lineNumber + 1) . ": Unknown category '{$category}'";
                        continue;
                    }

                    // Check for duplicates
                    $rowKey = $dateStr . '|' . $description . '|' . $amountStr . '|' . $category;
                    if (in_array($rowKey, $processedRows)) {
                        $skippedCount++;
                        $skippedReasons[] = "Line " . ($lineNumber + 1) . ": Duplicate row";
                        continue;
                    }
                    $processedRows[] = $rowKey;

                    // Validate expense data
                    $this->validateExpenseData($amount, $description, $date, $category);

                    // Create and save expense
                    $amountCents = (int) round($amount * 100);
                    $expense = new Expense(null, $user->id, $date, $category, $amountCents, $description);
                    $this->expenses->save($expense);

                    $importedCount++;

                } catch (Exception $e) {
                    $skippedCount++;
                    $skippedReasons[] = "Line " . ($lineNumber + 1) . ": " . $e->getMessage();
                }
            }

            // Commit transaction
            $this->expenses->commit();

            // Log results
            if ($skippedCount > 0) {
                $this->logger->warning("CSV Import: Skipped {$skippedCount} rows", [
                    'user_id' => $user->id,
                    'reasons' => $skippedReasons
                ]);
            }

            $this->logger->info("CSV Import completed: {$importedCount} expenses imported", [
                'user_id' => $user->id,
                'imported_count' => $importedCount,
                'skipped_count' => $skippedCount
            ]);

            return $importedCount;

        } catch (Exception $e) {
            // Rollback transaction on error
            $this->expenses->rollback();

            $this->logger->error("CSV Import failed", [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            throw new InvalidArgumentException('CSV import failed: ' . $e->getMessage());
        }
    }
}
