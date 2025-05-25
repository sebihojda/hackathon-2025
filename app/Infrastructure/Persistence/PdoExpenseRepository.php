<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Expense;
use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use Exception;
use PDO;

class PdoExpenseRepository implements ExpenseRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * @throws Exception
     */
    public function find(int $id): ?Expense
    {
        $query = 'SELECT * FROM expenses WHERE id = :id';
        $statement = $this->pdo->prepare($query);
        $statement->execute(['id' => $id]);
        $data = $statement->fetch();
        if (false === $data) {
            return null;
        }

        return $this->createExpenseFromData($data);
    }

    public function save(Expense $expense): void
    {
        if ($expense->id === null) {
            // Insert new expense
            $query = 'INSERT INTO expenses (user_id, date, category, amount_cents, description) VALUES (?, ?, ?, ?, ?)';
            $statement = $this->pdo->prepare($query);
            $statement->execute([
                $expense->userId,
                $expense->date->format('Y-m-d H:i:s'),
                $expense->category,
                $expense->amountCents,
                $expense->description,
            ]);

            $expense->id = (int) $this->pdo->lastInsertId();
        } else {
            // Update existing expense
            $query = 'UPDATE expenses SET user_id = ?, date = ?, category = ?, amount_cents = ?, description = ? WHERE id = ?';
            $statement = $this->pdo->prepare($query);
            $statement->execute([
                $expense->userId,
                $expense->date->format('Y-m-d H:i:s'),
                $expense->category,
                $expense->amountCents,
                $expense->description,
                $expense->id,
            ]);
        }
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM expenses WHERE id=?');
        $statement->execute([$id]);
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

    /**
     * @throws Exception
     */
    public function findBy(array $criteria, int $from, int $limit): array
    {
        $conditions = [];
        $params = [];

        foreach ($criteria as $field => $value) {
            if ($field === 'user_id') {
                $conditions[] = 'user_id = ?';
                $params[] = $value;
            } elseif ($field === 'year') {
                $conditions[] = 'strftime("%Y", date) = ?';
                $params[] = $value;
            } elseif ($field === 'month') {
                $conditions[] = 'strftime("%m", date) = ?';
                $params[] = sprintf('%02d', $value);
            }
        }

        $whereClause = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
        $query = "SELECT * FROM expenses {$whereClause} ORDER BY date DESC LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $from;

        $statement = $this->pdo->prepare($query);
        $statement->execute($params);

        $expenses = [];
        while ($data = $statement->fetch()) {
            $expenses[] = $this->createExpenseFromData($data);
        }

        return $expenses;
    }

    public function countBy(array $criteria): int
    {
        $conditions = [];
        $params = [];

        foreach ($criteria as $field => $value) {
            if ($field === 'user_id') {
                $conditions[] = 'user_id = ?';
                $params[] = $value;
            } elseif ($field === 'year') {
                $conditions[] = 'strftime("%Y", date) = ?';
                $params[] = $value;
            } elseif ($field === 'month') {
                $conditions[] = 'strftime("%m", date) = ?';
                $params[] = sprintf('%02d', $value);
            }
        }

        $whereClause = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
        $query = "SELECT COUNT(*) FROM expenses {$whereClause}";

        $statement = $this->pdo->prepare($query);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    public function listExpenditureYears(int $userId): array
    {
        $query = 'SELECT DISTINCT strftime("%Y", date) as year FROM expenses WHERE user_id = ? ORDER BY year DESC';
        $statement = $this->pdo->prepare($query);
        $statement->execute([$userId]);

        $years = [];
        while ($row = $statement->fetch()) {
            $years[] = (int) $row['year'];
        }

        // Always include current year
        $currentYear = (int) date('Y');
        if (!in_array($currentYear, $years)) {
            array_unshift($years, $currentYear);
        }

        return $years;
    }

    public function sumAmountsByCategory(array $criteria): array
    {
        $conditions = [];
        $params = [];

        foreach ($criteria as $field => $value) {
            if ($field === 'user_id') {
                $conditions[] = 'user_id = ?';
                $params[] = $value;
            } elseif ($field === 'year') {
                $conditions[] = 'strftime("%Y", date) = ?';
                $params[] = $value;
            } elseif ($field === 'month') {
                $conditions[] = 'strftime("%m", date) = ?';
                $params[] = sprintf('%02d', $value);
            }
        }

        $whereClause = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
        $query = "SELECT category, SUM(amount_cents) as total FROM expenses {$whereClause} GROUP BY category";

        $statement = $this->pdo->prepare($query);
        $statement->execute($params);

        $result = [];
        while ($row = $statement->fetch()) {
            $result[$row['category']] = (float) $row['total'] / 100;
        }

        return $result;
    }

    public function averageAmountsByCategory(array $criteria): array
    {
        $conditions = [];
        $params = [];

        foreach ($criteria as $field => $value) {
            if ($field === 'user_id') {
                $conditions[] = 'user_id = ?';
                $params[] = $value;
            } elseif ($field === 'year') {
                $conditions[] = 'strftime("%Y", date) = ?';
                $params[] = $value;
            } elseif ($field === 'month') {
                $conditions[] = 'strftime("%m", date) = ?';
                $params[] = sprintf('%02d', $value);
            }
        }

        $whereClause = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
        $query = "SELECT category, AVG(amount_cents) as average FROM expenses {$whereClause} GROUP BY category";

        $statement = $this->pdo->prepare($query);
        $statement->execute($params);

        $result = [];
        while ($row = $statement->fetch()) {
            $result[$row['category']] = (float) $row['average'] / 100;
        }

        return $result;
    }

    public function sumAmounts(array $criteria): float
    {
        $conditions = [];
        $params = [];

        foreach ($criteria as $field => $value) {
            if ($field === 'user_id') {
                $conditions[] = 'user_id = ?';
                $params[] = $value;
            } elseif ($field === 'year') {
                $conditions[] = 'strftime("%Y", date) = ?';
                $params[] = $value;
            } elseif ($field === 'month') {
                $conditions[] = 'strftime("%m", date) = ?';
                $params[] = sprintf('%02d', $value);
            }
        }

        $whereClause = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
        $query = "SELECT SUM(amount_cents) as total FROM expenses {$whereClause}";

        $statement = $this->pdo->prepare($query);
        $statement->execute($params);

        $result = $statement->fetchColumn();
        return $result ? (float) $result / 100 : 0.0;
    }

    /**
     * @throws Exception
     */
    private function createExpenseFromData(mixed $data): Expense
    {
        return new Expense(
            $data['id'],
            $data['user_id'],
            new DateTimeImmutable($data['date']),
            $data['category'],
            $data['amount_cents'],
            $data['description'],
        );
    }
}
