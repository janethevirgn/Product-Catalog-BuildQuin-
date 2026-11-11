-- Run once on the existing product_catalog database before using the updated CRUD files.
-- The old category text is converted to category_id and removed after backfilling.

ALTER TABLE categories
    ADD UNIQUE KEY uq_categories_category_name (category_name);

INSERT IGNORE INTO categories (category_name)
SELECT DISTINCT products.category
FROM products
LEFT JOIN categories ON categories.category_name = products.category
WHERE categories.id IS NULL;

ALTER TABLE products
    ADD COLUMN category_id INT NULL AFTER category;

UPDATE products
JOIN categories ON categories.category_name = products.category
SET products.category_id = categories.id;

ALTER TABLE products
    MODIFY category_id INT NOT NULL,
    ADD KEY idx_products_category_id (category_id),
    ADD UNIQUE KEY uq_products_product_code (product_code),
    ADD CONSTRAINT fk_products_category
        FOREIGN KEY (category_id) REFERENCES categories (id)
        ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE products
    DROP COLUMN category;
