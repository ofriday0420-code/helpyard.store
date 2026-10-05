ALTER TABLE cart_items
    ADD COLUMN product_variant_id BIGINT UNSIGNED NULL AFTER product_id,
    ADD CONSTRAINT fk_cart_items_variant
        FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE;
