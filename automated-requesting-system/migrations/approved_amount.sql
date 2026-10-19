-- Store the amount approved during Accounting Checking.
ALTER TABLE forms
    ADD COLUMN IF NOT EXISTS approved_amount DECIMAL(15, 2) DEFAULT NULL;