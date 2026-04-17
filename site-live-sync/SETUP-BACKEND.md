# My Hillside Backend Setup

This setup gives you:

- real order storage
- contact, newsletter, and bulk inquiry storage
- public order tracking
- a simple admin dashboard

## Files to upload

Upload these new folders/files to your live Hostinger site:

- `api/`
- `admin/`
- `storage/`
- `config.php`
- updated `assets/js/site-config.js`
- updated `assets/js/app.js`

## Important first step

Open `config.php` and change:

- `admin_username`
- `admin_password`

Do this before launch.

## Accessing the admin

After upload, open:

- `https://myhillside.in/admin/`

Log in with the username and password from `config.php`.

## How orders will work

1. Customer adds products to cart
2. Customer completes checkout
3. Order is saved into `storage/orders.json`
4. You open `/admin/`
5. You update status to:
   - Confirmed
   - Packed
   - Shipped
   - Delivered
6. Customer can track using the order ID on the Contact page

## How inquiries will work

- Contact form, newsletter form, and bulk inquiry form all save into `storage/forms.json`
- In admin, you can switch tabs to view:
  - Bulk
  - Contact
  - Newsletter

## Recommended Hostinger permissions

If saving does not work, ensure the `storage/` folder is writable by PHP.

Typical working permissions:

- folders: `755` or `775`
- files: `644`

If Hostinger blocks writing, set `storage/` to `775`.

## Daily order handling workflow

1. Open `https://myhillside.in/admin/`
2. Review new orders
3. Confirm payment status
4. Pack the order
5. Update order status
6. Share courier/tracking manually with the customer if needed
7. Mark as delivered after completion

## Future upgrades

Later, we can add:

- email notifications
- Razorpay payment verification
- Shiprocket integration
- export to CSV
- product management in admin
- customer order history
