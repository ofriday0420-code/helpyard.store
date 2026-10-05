INSERT INTO courses (product_id)
SELECT id FROM products WHERE product_type = 'course'
ON DUPLICATE KEY UPDATE product_id = VALUES(product_id);
