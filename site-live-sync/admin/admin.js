const adminApi = async (path, options = {}) => {
  const response = await fetch(`../api/${path}`, {
    headers: {
      "Content-Type": "application/json",
      ...(options.headers || {})
    },
    ...options
  });

  const text = await response.text();
  let data = {};

  try {
    data = text ? JSON.parse(text) : {};
  } catch {
    data = { message: text || "Unexpected response" };
  }

  if (!response.ok) {
    throw new Error(data.message || "Request failed");
  }

  return data;
};

const loginCard = document.querySelector("[data-login-card]");
const adminPanel = document.querySelector("[data-admin-panel]");
const loginForm = document.querySelector("[data-login-form]");
const loginMessage = document.querySelector("[data-login-message]");

const orderStatuses = [
  "Confirmed - Preparing for dispatch",
  "Packed - Ready to ship",
  "Shipped",
  "Out for delivery",
  "Delivered",
  "Cancelled"
];

const paymentStatuses = ["pending", "paid", "failed", "refunded"];

const renderEntries = (target, entries, formatter) => {
  const node = document.querySelector(target);
  if (!node) return;

  if (!entries.length) {
    node.innerHTML = "<div class=\"admin-list-item\"><p>No entries yet.</p></div>";
    return;
  }

  node.innerHTML = entries.map(formatter).join("");
};

const renderOrders = (orders) => {
  renderEntries("[data-orders-list]", orders, (order) => `
    <article class="admin-list-item">
      <h3>${order.id}</h3>
      <div class="admin-meta">
        <div><strong>${order.customer.name || "Customer"}</strong><span>${order.customer.phone || ""}</span></div>
        <div><strong>${order.paymentMethod?.toUpperCase() || "COD"}</strong><span>${order.paymentStatus || "pending"}</span></div>
        <div><strong>Total</strong><span>₹${Math.round(order.total || 0)}</span></div>
        <div><strong>Created</strong><span>${new Date(order.createdAt).toLocaleString()}</span></div>
      </div>
      <div class="admin-items">
        ${order.items.map((item) => `<p>${item.name} | ${item.variant} | Qty ${item.quantity}</p>`).join("")}
        <p><strong>Address:</strong> ${order.customer.address || ""}, ${order.customer.city || ""} - ${order.customer.pincode || ""}</p>
      </div>
      <div class="admin-order-actions">
        <select data-order-status="${order.id}">
          ${orderStatuses.map((status) => `<option value="${status}" ${status === order.status ? "selected" : ""}>${status}</option>`).join("")}
        </select>
        <select data-payment-status="${order.id}">
          ${paymentStatuses.map((status) => `<option value="${status}" ${status === order.paymentStatus ? "selected" : ""}>${status}</option>`).join("")}
        </select>
        <textarea data-order-note="${order.id}" placeholder="Admin note">${order.adminNote || ""}</textarea>
        <button class="button button-primary" type="button" data-save-order="${order.id}">Save updates</button>
      </div>
      <p class="admin-inline-note" data-order-feedback="${order.id}"></p>
    </article>
  `);
};

const renderFormEntries = (target, entries) => {
  renderEntries(target, entries, (entry) => `
    <article class="admin-list-item">
      <h3>${entry.formType.toUpperCase()}</h3>
      <div class="admin-items">
        ${Object.entries(entry.payload || {}).map(([key, value]) => `<p><strong>${key}:</strong> ${value || "-"}</p>`).join("")}
      </div>
      <p>${new Date(entry.createdAt).toLocaleString()}</p>
    </article>
  `);
};

const loadDashboard = async () => {
  const [ordersData, bulkData, contactData, newsletterData] = await Promise.all([
    adminApi("orders.php"),
    adminApi("forms.php?type=bulk"),
    adminApi("forms.php?type=contact"),
    adminApi("forms.php?type=newsletter")
  ]);

  renderOrders(ordersData.orders || []);
  renderFormEntries("[data-bulk-list]", bulkData.entries || []);
  renderFormEntries("[data-contact-list]", contactData.entries || []);
  renderFormEntries("[data-newsletter-list]", newsletterData.entries || []);
};

const setAuthenticated = (isAuthenticated) => {
  loginCard.classList.toggle("is-hidden", isAuthenticated);
  adminPanel.classList.toggle("is-hidden", !isAuthenticated);
};

loginForm?.addEventListener("submit", async (event) => {
  event.preventDefault();
  loginMessage.textContent = "Signing in...";
  const data = Object.fromEntries(new FormData(loginForm).entries());

  try {
    await adminApi("login.php", {
      method: "POST",
      body: JSON.stringify(data)
    });
    loginMessage.textContent = "";
    setAuthenticated(true);
    await loadDashboard();
  } catch (error) {
    loginMessage.textContent = error.message || "Login failed.";
  }
});

document.querySelector("[data-logout]")?.addEventListener("click", async () => {
  await adminApi("login.php", { method: "DELETE" });
  setAuthenticated(false);
});

document.addEventListener("click", async (event) => {
  const saveButton = event.target.closest("[data-save-order]");
  if (!saveButton) return;

  const orderId = saveButton.dataset.saveOrder;
  const status = document.querySelector(`[data-order-status="${orderId}"]`)?.value || "";
  const paymentStatus = document.querySelector(`[data-payment-status="${orderId}"]`)?.value || "";
  const adminNote = document.querySelector(`[data-order-note="${orderId}"]`)?.value || "";
  const feedback = document.querySelector(`[data-order-feedback="${orderId}"]`);

  if (feedback) feedback.textContent = "Saving...";

  try {
    await adminApi("orders.php", {
      method: "PATCH",
      body: JSON.stringify({ orderId, status, paymentStatus, adminNote })
    });
    if (feedback) feedback.textContent = "Updated successfully.";
  } catch (error) {
    if (feedback) feedback.textContent = error.message || "Could not update order.";
  }
});

document.querySelectorAll("[data-admin-tab]").forEach((button) => {
  button.addEventListener("click", () => {
    document.querySelectorAll("[data-admin-tab]").forEach((tab) => tab.classList.remove("is-active"));
    document.querySelectorAll("[data-admin-section]").forEach((section) => section.classList.add("is-hidden"));
    button.classList.add("is-active");
    document.querySelector(`[data-admin-section="${button.dataset.adminTab}"]`)?.classList.remove("is-hidden");
  });
});

document.addEventListener("DOMContentLoaded", async () => {
  try {
    const status = await adminApi("login.php");
    if (status.authenticated) {
      setAuthenticated(true);
      await loadDashboard();
    }
  } catch {
    setAuthenticated(false);
  }
});
