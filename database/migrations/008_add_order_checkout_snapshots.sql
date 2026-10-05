ALTER TABLE orders
    ADD COLUMN shipping_full_name VARCHAR(120) NOT NULL DEFAULT '',
    ADD COLUMN shipping_phone VARCHAR(30) NOT NULL DEFAULT '',
    ADD COLUMN shipping_address_line_1 VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN shipping_address_line_2 VARCHAR(255) NULL,
    ADD COLUMN shipping_city VARCHAR(80) NOT NULL DEFAULT '',
    ADD COLUMN shipping_postal_code VARCHAR(20) NULL,
    ADD COLUMN shipping_country VARCHAR(80) NOT NULL DEFAULT '',
    ADD COLUMN reservation_expires_at DATETIME NULL,
    ADD INDEX idx_orders_reservation_expiry (status, reservation_expires_at);

ALTER TABLE order_items
    ADD COLUMN product_name VARCHAR(180) NOT NULL DEFAULT '',
    ADD COLUMN variant_name VARCHAR(180) NULL,
    ADD COLUMN product_variant_id BIGINT UNSIGNED NULL;
