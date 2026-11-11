-- Run once on an existing product_catalog database.
ALTER TABLE users
    ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL;