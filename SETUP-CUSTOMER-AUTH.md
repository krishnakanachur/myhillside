# My Hillside Customer Auth Setup

This setup adds:

- customer signup and login
- password-based checkout protection
- customer-linked orders
- customer order history page

## 1. Create the database in Hostinger

Create a MySQL database and user in Hostinger. Save:

- database host
- database name
- database username
- database password

## 2. Update `config.php`

Fill in:

- `db_host`
- `db_name`
- `db_user`
- `db_pass`

## 3. Import `database.sql`

Use phpMyAdmin and import:

- `database.sql`

It creates:

- `customers`
- `orders`

## 4. Upload these files

- `config.php`
- `database.sql`
- `SETUP-CUSTOMER-AUTH.md`
- `account.html`
- `api/bootstrap.php`
- `api/customer-auth.php`
- `api/orders.php`
- `assets/js/app.js`
- `assets/css/styles.css`

## 5. Customer flow

1. Visitor browses freely
2. Clicks `Proceed to Checkout`
3. Login/signup modal appears
4. Customer logs in or creates account
5. Checkout opens
6. Order is saved against that customer
7. Customer can later view `/account.html`

## 6. Daily handling

- Customers manage their own order history via `account.html`
- Admin still uses `/admin/`
- Orders remain visible in the admin dashboard
