USE royalfamilytz;

ALTER TABLE transactions
  ADD COLUMN IF NOT EXISTS order_reference VARCHAR(80) NULL,
  ADD COLUMN IF NOT EXISTS provider_ref VARCHAR(120) NULL,
  ADD COLUMN IF NOT EXISTS channel VARCHAR(80) NULL,
  ADD COLUMN IF NOT EXISTS failure_message VARCHAR(255) NULL;

UPDATE transactions
SET order_reference = CONCAT('LEGACY', id)
WHERE order_reference IS NULL;

ALTER TABLE transactions
  MODIFY order_reference VARCHAR(80) NOT NULL,
  ADD UNIQUE KEY uq_transactions_order_reference (order_reference);
