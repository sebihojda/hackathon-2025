-- Index for expenses by user_id (most common filter)
CREATE INDEX IF NOT EXISTS idx_expenses_user_id ON expenses (user_id);

-- Index for expenses by date (used for monthly filtering and sorting)
CREATE INDEX IF NOT EXISTS idx_expenses_date ON expenses (date);

-- Composite index for user_id + date (optimal for monthly expense queries)
CREATE INDEX IF NOT EXISTS idx_expenses_user_date ON expenses (user_id, date);

-- Index for category (used in aggregations and filtering)
CREATE INDEX IF NOT EXISTS idx_expenses_category ON expenses (category);

-- Composite index for user_id + category (optimal for category summaries)
CREATE INDEX IF NOT EXISTS idx_expenses_user_category ON expenses (user_id, category);

-- Composite index for duplicate detection in CSV import
CREATE INDEX IF NOT EXISTS idx_expenses_duplicate_check ON expenses (user_id, date, description, amount_cents, category);