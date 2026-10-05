ALTER TABLE orders
    MODIFY COLUMN status ENUM(
        'pending',
        'payment_pending',
        'payment_review',
        'paid',
        'processing',
        'shipped',
        'delivered',
        'digital_ready',
        'completed',
        'cancelled',
        'refunded',
        'payment_failed'
    ) NOT NULL DEFAULT 'pending';

CREATE TABLE IF NOT EXISTS payment_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(40) NOT NULL,
    transaction_id VARCHAR(30) NOT NULL UNIQUE,
    session_key VARCHAR(255) NULL,
    gateway_url VARCHAR(2048) NULL,
    validation_id VARCHAR(100) NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'BDT',
    status ENUM('initiating', 'pending', 'paid', 'failed', 'cancelled', 'review_required') NOT NULL DEFAULT 'initiating',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX uq_payment_provider_session (provider, session_key),
    INDEX idx_payment_order_status (order_id, status),
    CONSTRAINT fk_payment_transactions_order
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
