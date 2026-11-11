-- OPTIONAL. You normally do NOT need this file.
-- The Admin registers on the Admin / Staff registration page (role: Admin, password: see db/staff_roles.php).
-- Only one Admin can exist. Use this only if an existing account must be made the Admin
-- and no other Admin exists yet.
UPDATE users
SET role = 'admin', staff_role = NULL
WHERE email = 'replace-with-your-email@example.com'
  AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM users WHERE role = 'admin') AS existing_admin);
