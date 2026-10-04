INSERT INTO categories (name, slug, parent_id) VALUES
    ('Courses', 'courses', NULL),
    ('Ready Websites', 'ready-websites', NULL),
    ('Official Software', 'official-software', NULL),
    ('Books', 'books', NULL),
    ('Cyber-Security Devices', 'cyber-security-devices', NULL)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO products (category_id, product_type, name, slug, short_description, description, price, compare_price, stock_quantity, is_active)
SELECT c.id, 'course', 'Advanced Security Bootcamp', 'advanced-security-bootcamp', 'Hands-on cyber security learning path', 'Course curriculum with modules, practice labs, and assignments.', 2990.00, 3990.00, 100, 1
FROM categories c WHERE c.slug = 'courses'
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO products (category_id, product_type, name, slug, short_description, description, price, compare_price, stock_quantity, is_active)
SELECT c.id, 'website', 'Business Website Starter', 'business-website-starter', 'Ready-made business website package', 'Landing page and conversion-focused web template.', 14900.00, 17900.00, 15, 1
FROM categories c WHERE c.slug = 'ready-websites'
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO products (category_id, product_type, name, slug, short_description, description, price, compare_price, stock_quantity, is_active)
SELECT c.id, 'software', 'Helpyard CRM Dashboard', 'helpyard-crm-dashboard', 'Business operations and customer dashboard', 'Operational software package with modules and support.', 18900.00, 21900.00, 25, 1
FROM categories c WHERE c.slug = 'official-software'
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO products (category_id, product_type, name, slug, short_description, description, price, compare_price, stock_quantity, is_active)
SELECT c.id, 'book', 'Modern E-Commerce Operations', 'modern-e-commerce-operations', 'Strategy and operations guide', 'A practical roadmap for e-commerce operations and scaling.', 1200.00, 1500.00, 100, 1
FROM categories c WHERE c.slug = 'books'
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO products (category_id, product_type, name, slug, short_description, description, price, compare_price, stock_quantity, is_active)
SELECT c.id, 'physical', 'Security Device Kit', 'security-device-kit', 'Complete home security package', 'USB and hardware kit for monitoring and protection.', 6500.00, 7800.00, 40, 1
FROM categories c WHERE c.slug = 'cyber-security-devices'
ON DUPLICATE KEY UPDATE name = VALUES(name);
