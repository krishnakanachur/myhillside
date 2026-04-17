<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Hillside Admin</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
  <link rel="stylesheet" href="admin.css">
  <script src="admin.js" defer></script>
</head>
<body class="admin-body">
  <main class="admin-shell">
    <section class="admin-card" data-login-card>
      <span class="eyebrow">Admin login</span>
      <h1>My Hillside Dashboard</h1>
      <p>Manage orders, contact inquiries, bulk leads, and newsletter signups from one place.</p>
      <form class="admin-form" data-login-form>
        <input type="text" name="username" placeholder="Username" value="admin" required>
        <input type="password" name="password" placeholder="Password" required>
        <button class="button button-primary" type="submit">Sign in</button>
      </form>
      <p class="admin-message" data-login-message></p>
    </section>

    <section class="admin-panel is-hidden" data-admin-panel>
      <div class="admin-topbar">
        <div>
          <span class="eyebrow">Operations</span>
          <h1>My Hillside Admin</h1>
        </div>
        <button class="button button-secondary" type="button" data-logout>Logout</button>
      </div>

      <div class="admin-tabs">
        <button class="filter-button is-active" type="button" data-admin-tab="orders">Orders</button>
        <button class="filter-button" type="button" data-admin-tab="bulk">Bulk Inquiries</button>
        <button class="filter-button" type="button" data-admin-tab="contact">Contact</button>
        <button class="filter-button" type="button" data-admin-tab="newsletter">Newsletter</button>
      </div>

      <div class="admin-grid">
        <section class="admin-section" data-admin-section="orders">
          <div class="section-heading">
            <span class="eyebrow">Orders</span>
            <h2>Recent customer orders</h2>
          </div>
          <div class="admin-list" data-orders-list></div>
        </section>

        <section class="admin-section is-hidden" data-admin-section="bulk">
          <div class="section-heading">
            <span class="eyebrow">Bulk</span>
            <h2>Wholesale &amp; HoReCa inquiries</h2>
          </div>
          <div class="admin-list" data-bulk-list></div>
        </section>

        <section class="admin-section is-hidden" data-admin-section="contact">
          <div class="section-heading">
            <span class="eyebrow">Contact</span>
            <h2>Customer messages</h2>
          </div>
          <div class="admin-list" data-contact-list></div>
        </section>

        <section class="admin-section is-hidden" data-admin-section="newsletter">
          <div class="section-heading">
            <span class="eyebrow">Newsletter</span>
            <h2>Subscriber leads</h2>
          </div>
          <div class="admin-list" data-newsletter-list></div>
        </section>
      </div>
    </section>
  </main>
</body>
</html>
