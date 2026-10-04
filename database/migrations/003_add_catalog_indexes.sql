ALTER TABLE products
    ADD INDEX idx_products_catalog (is_active, category_id, product_type, id);

ALTER TABLE categories
    ADD INDEX idx_categories_parent (parent_id);
