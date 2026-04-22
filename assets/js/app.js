const config = window.MH_CONFIG || {};
const products = window.MH_PRODUCTS || [];
const cartKey = "mh_cart";
const ordersKey = "mh_orders";
const backendBase = config.backendBase || "";
const siteGateKey = "mh_site_gate_access";

const currency = new Intl.NumberFormat("en-IN", {
  style: "currency",
  currency: config.currency || "INR",
  maximumFractionDigits: 0
});

const getCart = () => {
  try {
    return JSON.parse(localStorage.getItem(cartKey)) || [];
  } catch {
    return [];
  }
};

const saveCart = (cart) => localStorage.setItem(cartKey, JSON.stringify(cart));

const getOrders = () => {
  try {
    return JSON.parse(localStorage.getItem(ordersKey)) || [];
  } catch {
    return [];
  }
};

const saveOrders = (orders) => localStorage.setItem(ordersKey, JSON.stringify(orders));
const hasSiteAccess = () => Boolean(localStorage.getItem(siteGateKey));

const findProduct = (id) => products.find((product) => product.id === id);

const createProductCard = (product) => `
  <article class="product-card reveal" data-category="${product.category}">
    <div class="product-image-wrap">
      <img src="${product.image}" alt="${product.name} ${product.variant}" loading="lazy">
    </div>
    <div class="product-meta">
      <span>${product.tag}</span>
      <strong>${product.variant}</strong>
    </div>
    <h3>${product.name}</h3>
    <p>${product.description}</p>
    <div class="product-footer">
      <div>
        <strong class="product-price">${currency.format(product.price)}</strong>
        <span class="product-note">${product.note}</span>
      </div>
      <button class="button button-secondary" type="button" data-add-to-cart="${product.id}">Add to Cart</button>
    </div>
  </article>
`;

const renderProductGrids = () => {
  document.querySelectorAll("[data-product-grid]").forEach((grid) => {
    const type = grid.dataset.productGrid;
    const visibleProducts = type === "featured" ? products.slice(0, 4) : products;
    grid.innerHTML = visibleProducts.map(createProductCard).join("");
  });
};

const renderCart = () => {
  const cart = getCart();
  const itemsNode = document.querySelector("[data-cart-items]");
  const countNodes = document.querySelectorAll("[data-cart-count]");
  const subtotalNode = document.querySelector("[data-cart-subtotal]");
  const shippingNode = document.querySelector("[data-cart-shipping]");
  const totalNode = document.querySelector("[data-cart-total]");

  const count = cart.reduce((sum, item) => sum + item.quantity, 0);
  const subtotal = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
  const shipping = subtotal >= (config.freeShippingThreshold || 1200) || subtotal === 0 ? 0 : (config.shippingFee || 80);
  const total = subtotal + shipping;

  countNodes.forEach((node) => {
    node.textContent = count;
  });

  if (itemsNode) {
    if (cart.length === 0) {
      itemsNode.innerHTML = "<p>Your cart is currently empty.</p>";
    } else {
      itemsNode.innerHTML = cart.map((item) => `
        <article class="cart-item">
          <img src="${item.image}" alt="${item.name}">
          <div>
            <strong>${item.name}</strong>
            <p>${item.variant} x ${item.quantity}</p>
            <p>${currency.format(item.price * item.quantity)}</p>
          </div>
          <button type="button" data-remove-cart-item="${item.id}">Remove</button>
        </article>
      `).join("");
    }
  }

  if (subtotalNode) subtotalNode.textContent = currency.format(subtotal);
  if (shippingNode) shippingNode.textContent = shipping === 0 ? "Free" : currency.format(shipping);
  if (totalNode) totalNode.textContent = currency.format(total);
};

const addToCart = (id) => {
  if (!hasSiteAccess()) {
    initSiteGate(() => addToCart(id));
    return;
  }

  const product = findProduct(id);
  if (!product) return;

  const cart = getCart();
  const existing = cart.find((item) => item.id === id);

  if (existing) {
    existing.quantity += 1;
  } else {
    cart.push({ ...product, quantity: 1 });
  }

  saveCart(cart);
  renderCart();
  openCart();
};

const removeFromCart = (id) => {
  const cart = getCart().filter((item) => item.id !== id);
  saveCart(cart);
  renderCart();
};

const setOpenState = (node, isOpen) => {
  if (!node) return;
  node.classList.toggle("is-open", isOpen);
  node.setAttribute("aria-hidden", String(!isOpen));
};

const cartDrawer = () => document.querySelector("[data-cart-drawer]");
const checkoutModal = () => document.querySelector("[data-checkout-modal]");

const resetCheckoutState = () => {
  const form = document.querySelector("[data-checkout-form]");
  const successPanel = document.querySelector("[data-checkout-success]");

  if (form) {
    form.hidden = false;
    form.reset();
  }

  if (successPanel) {
    successPanel.hidden = true;
  }
};

const openCart = () => setOpenState(cartDrawer(), true);
const closeCart = () => setOpenState(cartDrawer(), false);

const openCheckout = () => {
  if (!getCart().length) {
    alert("Add a product to your cart before checkout.");
    openCart();
    return;
  }

  if (!hasSiteAccess()) {
    initSiteGate(() => openCheckout());
    return;
  }

  resetCheckoutState();
  setOpenState(checkoutModal(), true);
};

const closeCheckout = () => {
  resetCheckoutState();
  setOpenState(checkoutModal(), false);
};

const bindCartActions = () => {
  document.addEventListener("click", (event) => {
    const addButton = event.target.closest("[data-add-to-cart]");
    if (addButton) addToCart(addButton.dataset.addToCart);

    const removeButton = event.target.closest("[data-remove-cart-item]");
    if (removeButton) removeFromCart(removeButton.dataset.removeCartItem);

    if (event.target.closest("[data-open-cart]")) openCart();
    if (event.target.closest("[data-close-cart]")) closeCart();
    if (event.target.closest("[data-open-checkout]")) {
      closeCart();
      openCheckout();
    }
    if (event.target.closest("[data-close-checkout]")) closeCheckout();
  });
};

const maybeLoadRazorpay = () => {
  if (!config.razorpayKey || document.querySelector("[data-razorpay-script]")) return;
  const script = document.createElement("script");
  script.src = "https://checkout.razorpay.com/v1/checkout.js";
  script.defer = true;
  script.dataset.razorpayScript = "true";
  document.head.appendChild(script);
};

const apiFetch = async (path, options = {}) => {
  const response = await fetch(`${backendBase}${path}`, {
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
    data = { message: text || "Unexpected response from server." };
  }

  if (!response.ok) {
    throw new Error(data.message || "Request failed.");
  }

  return data;
};

const submitCheckout = async (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  const successPanel = document.querySelector("[data-checkout-success]");
  const messageNode = document.querySelector("[data-checkout-message]");
  const formData = new FormData(form);
  const cart = getCart();

  if (!cart.length) {
    alert("Your cart is empty.");
    return;
  }

  const subtotal = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
  const shipping = subtotal >= (config.freeShippingThreshold || 1200) ? 0 : (config.shippingFee || 80);
  const total = subtotal + shipping;

  const order = {
    customer: Object.fromEntries(formData.entries()),
    items: cart,
    total,
    createdAt: new Date().toISOString(),
    status: "Confirmed - Preparing for dispatch"
  };

  const paymentMethod = formData.get("paymentMethod");
  maybeLoadRazorpay();

  if (config.razorpayKey && paymentMethod !== "cod" && window.Razorpay) {
    const payment = new window.Razorpay({
      key: config.razorpayKey,
      amount: total * 100,
      currency: config.currency || "INR",
      name: config.merchantName || "My Hillside",
      description: "Premium Malenadu coffee order",
      handler: async (paymentResponse) => {
        try {
          const created = await apiFetch("/orders.php", {
            method: "POST",
            body: JSON.stringify({
              ...order,
              paymentMethod,
              paymentStatus: "paid",
              razorpay: paymentResponse
            })
          });

          const orders = getOrders();
          orders.unshift(created.order);
          saveOrders(orders);
          saveCart([]);
          renderCart();
          form.hidden = true;
          successPanel.hidden = false;
          messageNode.textContent = `Your order ${created.order.id} has been placed successfully for ${currency.format(total)}.`;
        } catch (error) {
          alert(error.message || "Payment succeeded, but saving the order failed. Please contact support.");
        }
      },
      prefill: {
        name: formData.get("name"),
        email: formData.get("email"),
        contact: formData.get("phone")
      },
      theme: {
        color: "#314a37"
      }
    });

    payment.open();
    return;
  }

  try {
    const created = await apiFetch("/orders.php", {
      method: "POST",
      body: JSON.stringify({
        ...order,
        paymentMethod,
        paymentStatus: paymentMethod === "cod" ? "pending" : "initiated"
      })
    });

    const orders = getOrders();
    orders.unshift(created.order);
    saveOrders(orders);
    saveCart([]);
    renderCart();

    form.hidden = true;
    successPanel.hidden = false;
    messageNode.textContent = `Your order ${created.order.id} has been placed successfully for ${currency.format(total)} via ${String(paymentMethod).toUpperCase()}.`;
  } catch (error) {
    alert(error.message || "We could not place your order right now. Please try again.");
  }
};

const bindCheckout = () => {
  const form = document.querySelector("[data-checkout-form]");
  if (form) {
    form.addEventListener("submit", submitCheckout);
  }
};

const bindFilters = () => {
  const buttons = document.querySelectorAll("[data-filter]");
  if (!buttons.length) return;

  buttons.forEach((button) => {
    button.addEventListener("click", () => {
      buttons.forEach((item) => item.classList.remove("is-active"));
      button.classList.add("is-active");

      const filter = button.dataset.filter;
      document.querySelectorAll(".product-card[data-category]").forEach((card) => {
        card.classList.toggle("is-hidden", filter !== "all" && card.dataset.category !== filter);
      });
    });
  });
};

const handleFormSubmission = async (form) => {
  const payload = Object.fromEntries(new FormData(form).entries());
  const formType = form.dataset.formType;
  const storageKey = `mh_${formType}_entries`;
  const entries = JSON.parse(localStorage.getItem(storageKey) || "[]");
  entries.unshift({ ...payload, createdAt: new Date().toISOString() });
  localStorage.setItem(storageKey, JSON.stringify(entries));

  try {
    await apiFetch("/forms.php", {
      method: "POST",
      body: JSON.stringify({ formType, payload })
    });
  } catch {
    if (config.formsEndpoint) {
      try {
        await fetch(config.formsEndpoint, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ formType, payload })
        });
      } catch {
        // Falls back to local persistence so the front end remains functional.
      }
    }
  }

  form.reset();
  alert("Thanks. Your message has been recorded.");
};

const bindForms = () => {
  document.querySelectorAll("[data-form-type]").forEach((form) => {
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      await handleFormSubmission(event.currentTarget);
    });
  });
};

const bindTracking = () => {
  const form = document.querySelector("[data-track-order-form]");
  const result = document.querySelector("[data-tracking-result]");

  if (!form || !result) return;

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const orderId = new FormData(form).get("orderId");
    result.textContent = "Checking order status...";

    try {
      const data = await apiFetch(`/orders.php?track=${encodeURIComponent(orderId)}`);
      result.textContent = `${data.order.id}: ${data.order.status}. Last updated for ${data.order.customer.name}.`;
    } catch {
      const order = getOrders().find((entry) => entry.id === orderId);

      if (order) {
        result.textContent = `${order.id}: ${order.status}. Last updated for ${order.customer.name}.`;
      } else {
        result.textContent = "We could not find that order. Please check the order ID or contact support.";
      }
    }
  });
};

const bindMobileNav = () => {
  const toggle = document.querySelector(".mobile-nav-toggle");
  const nav = document.querySelector("[data-nav]");
  if (!toggle || !nav) return;

  toggle.addEventListener("click", () => {
    const isOpen = nav.classList.toggle("is-open");
    toggle.setAttribute("aria-expanded", String(isOpen));
  });
};

const initWhatsApp = () => {
  const link = document.querySelector("[data-whatsapp-link]");
  if (!link) return;

  if (!config.whatsappNumber) {
    link.classList.add("is-hidden");
    return;
  }

  const message = encodeURIComponent("Hello My Hillside, I would like to know more about your coffee blends.");
  link.href = `https://wa.me/${config.whatsappNumber}?text=${message}`;
  link.classList.remove("is-hidden");
};

const initReveal = () => {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add("is-visible");
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.2 });

  document.querySelectorAll(".reveal").forEach((node) => observer.observe(node));
};

const initSiteGate = (onComplete) => {
  if (hasSiteAccess()) {
    if (typeof onComplete === "function") onComplete();
    return;
  }

  if (document.querySelector(".site-gate")) return;

  const gate = document.createElement("div");
  gate.className = "site-gate";
  gate.innerHTML = `
    <div class="site-gate-backdrop"></div>
    <section class="site-gate-card" role="dialog" aria-modal="true" aria-labelledby="site-gate-title">
      <span class="eyebrow">Welcome</span>
      <h2 id="site-gate-title">Login to continue</h2>
      <p>Enter your details to continue before shopping.</p>
      <form class="site-gate-form" data-site-gate-form>
        <input type="text" name="name" placeholder="Your name" required>
        <input type="email" name="email" placeholder="Email address" required>
        <button class="button button-primary button-block" type="submit">Continue</button>
      </form>
      <p class="site-gate-note">This only unlocks shopping on this device and does not affect the admin login.</p>
    </section>
  `;

  document.body.appendChild(gate);
  document.body.style.overflow = "hidden";

  gate.querySelector("[data-site-gate-form]")?.addEventListener("submit", (event) => {
    event.preventDefault();
    const payload = Object.fromEntries(new FormData(event.currentTarget).entries());
    localStorage.setItem(siteGateKey, JSON.stringify({
      ...payload,
      unlockedAt: new Date().toISOString()
    }));
    gate.remove();
    document.body.style.overflow = "";
    if (typeof onComplete === "function") onComplete();
  });
};

document.addEventListener("DOMContentLoaded", () => {
  renderProductGrids();
  renderCart();
  bindCartActions();
  bindCheckout();
  bindFilters();
  bindForms();
  bindTracking();
  bindMobileNav();
  initWhatsApp();
  initReveal();
});
