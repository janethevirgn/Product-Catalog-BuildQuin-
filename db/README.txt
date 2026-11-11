BuildQuin database setup

The database name stays "product_catalog" (same as your XAMPP database). No table or
column names were renamed.

1. Start Apache and MySQL in XAMPP.
2. Copy the whole product_catalog folder into C:\xampp\htdocs\
3. NEW database: import db/setup.sql in phpMyAdmin.
   EXISTING database: you do not need to import anything. The first time you open the site,
   db/schema.php adds the missing tables (notifications, user_addresses, cart_items, orders,
   order_items) and converts the old roles by itself. Your records are kept.
4. Roles
     Admin = the owner/boss. Full control. Only ONE Admin account can exist.
     Staff = Clerk, Monitor, Administrative Assistant, Manager. Any number of people per role.
             They get the products, orders and feedback pages.
     User  = customer. Browse, add to cart, order, send feedback.
5. Admin and staff have no personal password. Each role has one password (see db/staff_roles.php):
     Admin: admin@123A    Clerk: clerk@123C    Monitor: monitor@123M
     Administrative Assistant: admin@123AA    Manager: manager@123M
6. Open http://localhost/product_catalog/html/register_staff.php (or the "Register as Admin / Staff" link
   on the login page, Admin / Staff tab). Fill in Full name, Gmail, Role and the Password of that role.
   After registering you are sent to the login page. Sign in with your Gmail and the same role password.
7. If you used the old roles, the old super_admin becomes Admin and the old admin becomes Staff.
   Old staff accounts pick up their role automatically the first time they sign in.
8. VAT is 3 percent. To change it, edit VAT_PERCENT in db/shop.php.
