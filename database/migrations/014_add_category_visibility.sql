ALTER TABLE categories
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER parent_id,
    ADD INDEX idx_categories_active_name (is_active, name);
