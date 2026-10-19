-- Store the Check Voucher entered during Accounting Checking for advance payments.
ALTER TABLE forms
    ADD COLUMN IF NOT EXISTS check_voucher VARCHAR(100) DEFAULT NULL;