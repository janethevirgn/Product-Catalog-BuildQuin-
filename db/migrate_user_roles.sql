-- OPTIONAL. The website now does this automatically the first time it connects to the database.
-- Run it only if you want to do it by hand in phpMyAdmin, and only ONCE.
--
-- Old roles:  super_admin (boss), admin (clerk), user
-- New roles:  admin (boss/owner), staff (clerk), user

ALTER TABLE users
    MODIFY role ENUM('super_admin', 'admin', 'staff', 'user') NOT NULL DEFAULT 'user';

UPDATE users SET role = 'staff' WHERE role = 'admin';
UPDATE users SET role = 'admin' WHERE role = 'super_admin';

ALTER TABLE users
    MODIFY role ENUM('admin', 'staff', 'user') NOT NULL DEFAULT 'user';
