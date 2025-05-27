-- Add payment fields to reservations table
ALTER TABLE reservations
ADD COLUMN payment_status ENUM('pending', 'completed', 'cancelled', 'failed') DEFAULT 'pending',
ADD COLUMN payment_order_id VARCHAR(255),
ADD COLUMN payment_id VARCHAR(255),
ADD COLUMN payment_amount DECIMAL(10,2),
ADD COLUMN payment_currency VARCHAR(3),
ADD COLUMN payer_email VARCHAR(255),
ADD COLUMN payer_id VARCHAR(255),
ADD COLUMN payer_name VARCHAR(255),
ADD COLUMN payment_initiated_at DATETIME,
ADD COLUMN payment_completed_at DATETIME,
ADD COLUMN payment_cancelled_at DATETIME,
ADD INDEX idx_payment_order_id (payment_order_id),
ADD INDEX idx_payment_status (payment_status); 