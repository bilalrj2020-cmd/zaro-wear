"use strict";

const products = Array.isArray(window.ZARO_WEAR_PRODUCTS)
  ? window.ZARO_WEAR_PRODUCTS
  : [];

const $ = selector => document.querySelector(selector);
const $$ = selector => document.querySelectorAll(selector);

const productGrid = $("#productGrid");
const productTotal = $("#productTotal");
const emptyState = $("#emptyState");
const searchInput = $("#searchInput");
const sortSelect = $("#sortSelect");

let activeCategory = "All";
let searchTerm = "";
let cart = Array.isArray(window.ZARO_WEAR_CART)
  ? window.ZARO_WEAR_CART.map(item => ({
      id: Number(item.id),
      size: String(item.size),
      quantity: Number(item.quantity)
    }))
  : [];

let selectedProductId = null;
let toastTimer;

const formatPrice = price =>
  "Rs. " + Number(price || 0).toLocaleString("en-PK", {
    maximumFractionDigits: 2
  });

function escapeHTML(value) {
  return String(value ?? "").replace(/[&<>"']/g, char => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;"
  })[char]);
}

function getProduct(id) {
  return products.find(product => Number(product.id) === Number(id));
}

function productImage(product) {
  if (product.image) return product.image;

  return "https://placehold.co/700x900/f0ede6/77766f?text=ZARO+WEAR";
}

function getSizes(product) {
  const category = String(product.category || "").toLowerCase();
  const name = String(product.name || "").toLowerCase();

  if (category === "accessories") {
    if (/shoe|sneaker|boot|footwear/.test(name)) {
      return ["39", "40", "41", "42", "43", "44"];
    }
    return ["One Size"];
  }

  if (category === "women") return ["XS", "S", "M", "L", "XL"];

  return ["S", "M", "L", "XL"];
}

function showToast(message) {
  const toast = $("#toast");
  if (!toast) return;

  toast.textContent = message;
  toast.classList.add("show");

  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove("show"), 2800);
}

function getFilteredProducts() {
  const result = products.filter(product => {
    const matchesCategory =
      activeCategory === "All" ||
      product.category.toLowerCase() === activeCategory.toLowerCase();

    const searchable =
      `${product.name} ${product.category} ${product.description || ""}`.toLowerCase();

    return matchesCategory && searchable.includes(searchTerm.toLowerCase());
  });

  switch (sortSelect.value) {
    case "low":
      result.sort((a, b) => Number(a.price) - Number(b.price));
      break;
    case "high":
      result.sort((a, b) => Number(b.price) - Number(a.price));
      break;
    case "name":
      result.sort((a, b) => a.name.localeCompare(b.name));
      break;
  }

  return result;
}

function renderProducts() {
  const visibleProducts = getFilteredProducts();

  productGrid.innerHTML = visibleProducts.map(product => `
    <article class="product-card">
      <div class="product-image" data-view="${Number(product.id)}"
           role="button" tabindex="0"
           aria-label="View ${escapeHTML(product.name)}">
        <img src="${escapeHTML(productImage(product))}"
             alt="${escapeHTML(product.name)}"
             loading="lazy"
             onerror="this.onerror=null;this.src='https://placehold.co/700x900/f0ede6/77766f?text=ZARO+WEAR'">
        ${product.badge
          ? `<span class="product-badge">${escapeHTML(product.badge)}</span>`
          : ""}
        <button class="quick-view" data-view="${Number(product.id)}"
                type="button">QUICK VIEW ↗</button>
      </div>

      <div class="product-info">
        <span class="product-category">${escapeHTML(product.category)}</span>
        <h3 class="product-title">${escapeHTML(product.name)}</h3>
        <div class="product-price">${formatPrice(product.price)}</div>
        <div class="product-bottom">
          <button class="add-button" data-add="${Number(product.id)}"
                  type="button">ADD TO BAG +</button>
        </div>
      </div>
    </article>
  `).join("");

  productTotal.textContent =
    `${visibleProducts.length} PRODUCT${visibleProducts.length === 1 ? "" : "S"}`;

  emptyState.hidden = visibleProducts.length !== 0;
}

function setCategory(category) {
  activeCategory = category;

  $$(".filter-btn").forEach(button => {
    const active = button.dataset.filter === category;
    button.classList.toggle("active", active);
    button.setAttribute("aria-pressed", String(active));
  });

  renderProducts();
}

function cartPayload() {
  return {
    items: cart.map(item => ({
      id: Number(item.id),
      size: String(item.size),
      quantity: Number(item.quantity)
    }))
  };
}
    async function syncCartWithPHP() {
    const body = new URLSearchParams();

    body.set("csrf_token", String(window.ZARO_WEAR_CSRF || ""));
    body.set("items", JSON.stringify(cartPayload().items));

    const response = await fetch("api/cart.php", {
        method: "POST",
        credentials: "same-origin",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
            "X-Requested-With": "XMLHttpRequest"
        },
        body: body.toString()
    });

    let data;

    try {
        data = await response.json();
    } catch {
        throw new Error("Server response samajh nahi aaya. PHP files check karein.");
    }

    if (!response.ok || !data.success) {
        throw new Error(data.message || "Cart sync nahi ho saka.");
    }

    return data;
}

async function saveCartOrRestore(previousCart) {
  renderCart();

  try {
    await syncCartWithPHP();
  } catch (error) {
    cart = previousCart;
    renderCart();
    showToast(error.message);
  }
}

function addToCart(productId, size) {
  const product = getProduct(productId);
  if (!product) return;

  if (Number(product.stock) < 1) {
    showToast("Yeh product filhal out of stock hai.");
    return;
  }

  const chosenSize = size || getSizes(product)[0];
  const previousCart = cart.map(item => ({ ...item }));

  const existing = cart.find(
    item => item.id === Number(productId) && item.size === chosenSize
  );

  const totalForProduct = cart
    .filter(item => item.id === Number(productId))
    .reduce((sum, item) => sum + item.quantity, 0);

  if (totalForProduct >= Number(product.stock)) {
    showToast("Available stock se zyada quantity add nahi ho sakti.");
    return;
  }

  if (existing) {
    existing.quantity += 1;
  } else {
    cart.push({
      id: Number(productId),
      size: chosenSize,
      quantity: 1
    });
  }

  saveCartOrRestore(previousCart);
  showToast(`${product.name} added to your bag`);
}

function changeQuantity(productId, size, amount) {
  const previousCart = cart.map(item => ({ ...item }));

  const item = cart.find(
    entry => entry.id === Number(productId) && entry.size === size
  );

  const product = getProduct(productId);
  if (!item || !product) return;

  const nextQuantity = item.quantity + amount;

  if (nextQuantity <= 0) {
    cart = cart.filter(entry => entry !== item);
  } else {
    const otherQuantity = cart
      .filter(entry => entry.id === Number(productId) && entry !== item)
      .reduce((sum, entry) => sum + entry.quantity, 0);

    if (nextQuantity + otherQuantity > Number(product.stock)) {
      showToast("Available stock se zyada quantity add nahi ho sakti.");
      return;
    }

    item.quantity = nextQuantity;
  }

  saveCartOrRestore(previousCart);
}

function removeCartItem(productId, size) {
  const previousCart = cart.map(item => ({ ...item }));

  cart = cart.filter(
    item => !(item.id === Number(productId) && item.size === size)
  );

  saveCartOrRestore(previousCart);
  showToast("Item removed from your bag");
}

function renderCart() {
  const totalQuantity = cart.reduce((sum, item) => sum + item.quantity, 0);

  $("#cartCount").textContent = totalQuantity;
  $("#drawerCount").textContent = `(${totalQuantity})`;

  const total = cart.reduce((sum, item) => {
    const product = getProduct(item.id);
    return sum + (product ? Number(product.price) * item.quantity : 0);
  }, 0);

  $("#cartSubtotal").textContent = formatPrice(total);

  if (cart.length === 0) {
    $("#cartItems").innerHTML =
      '<div class="cart-empty">Your bag is empty.<br>Find something you love.</div>';
    return;
  }

  $("#cartItems").innerHTML = cart.map(item => {
    const product = getProduct(item.id);
    if (!product) return "";

    return `
      <div class="cart-item">
        <img src="${escapeHTML(productImage(product))}"
             alt="${escapeHTML(product.name)}">
        <div>
          <h3>${escapeHTML(product.name)}</h3>
          <p>Size: ${escapeHTML(item.size)}</p>
          <p>${formatPrice(product.price)}</p>
          <div class="quantity-control">
            <button data-quantity="-1" data-id="${item.id}"
              data-size="${escapeHTML(item.size)}" type="button"
              aria-label="Decrease quantity">−</button>
            <span>${item.quantity}</span>
            <button data-quantity="1" data-id="${item.id}"
              data-size="${escapeHTML(item.size)}" type="button"
              aria-label="Increase quantity">+</button>
          </div>
          <button class="remove-item" data-remove="${item.id}"
            data-size="${escapeHTML(item.size)}" type="button">Remove</button>
        </div>
      </div>
    `;
  }).join("");
}

function openCart() {
  $("#overlay").classList.add("open");
  $("#cartDrawer").classList.add("open");
  $("#cartDrawer").removeAttribute("inert");
  $("#cartDrawer").setAttribute("aria-hidden", "false");
  document.body.classList.add("drawer-open");
  renderCart();
  $("#cartClose").focus();
}

function closeCart() {
  $("#overlay").classList.remove("open");
  $("#cartDrawer").classList.remove("open");
  $("#cartDrawer").setAttribute("inert", "");
  $("#cartDrawer").setAttribute("aria-hidden", "true");
  document.body.classList.remove("drawer-open");
  $("#cartToggle").focus();
}

function openProduct(productId) {
  const product = getProduct(productId);
  if (!product) return;

  selectedProductId = Number(productId);

  $("#productDetails").innerHTML = `
    <div class="detail-layout">
      <img src="${escapeHTML(productImage(product))}"
           alt="${escapeHTML(product.name)}">
      <div class="detail-copy">
        <span class="eyebrow">${escapeHTML(product.category)}</span>
        <h2>${escapeHTML(product.name)}</h2>
        <div class="product-price">${formatPrice(product.price)}</div>
        <p>${escapeHTML(product.description || "Discover this ZARO WEAR essential.")}</p>
        <label for="productSize">Choose size</label>
        <select id="productSize">
          ${getSizes(product).map(size =>
            `<option value="${escapeHTML(size)}">${escapeHTML(size)}</option>`
          ).join("")}
        </select>
        <button class="btn btn-dark" id="detailAddButton" type="button">
          ADD TO BAG ↗
        </button>
      </div>
    </div>
  `;

  const dialog = $("#productDialog");
  if (!dialog.open) dialog.showModal();
}

function closeProductDialog() {
  const dialog = $("#productDialog");
  if (dialog.open) dialog.close();
}

function toggleSearch() {
  const searchBar = $("#searchBar");
  searchBar.hidden = !searchBar.hidden;

  if (!searchBar.hidden) searchInput.focus();
}

function closeMobileMenu() {
  $("#mainNav").classList.remove("open");
  $("#menuToggle").setAttribute("aria-expanded", "false");
  $("#menuToggle").setAttribute("aria-label", "Open navigation");
}

$("#categoryFilters").addEventListener("click", event => {
  const button = event.target.closest("[data-filter]");
  if (button) setCategory(button.dataset.filter);
});

$$("[data-category-link]").forEach(link => {
  link.addEventListener("click", () => {
    setCategory(link.dataset.categoryLink);
    closeMobileMenu();
  });
});

productGrid.addEventListener("click", event => {
  const addButton = event.target.closest("[data-add]");

  if (addButton) {
    const product = getProduct(Number(addButton.dataset.add));
    if (product) openProduct(product.id);
    return;
  }

  const viewButton = event.target.closest("[data-view]");
  if (viewButton) openProduct(Number(viewButton.dataset.view));
});

productGrid.addEventListener("keydown", event => {
  if (
    event.target.matches("[data-view][role='button']") &&
    (event.key === "Enter" || event.key === " ")
  ) {
    event.preventDefault();
    openProduct(Number(event.target.dataset.view));
  }
});

searchInput.addEventListener("input", () => {
  searchTerm = searchInput.value.trim();
  renderProducts();
});

$("#searchSubmit").addEventListener("click", () => {
  searchTerm = searchInput.value.trim();
  renderProducts();
  $("#shop").scrollIntoView({ behavior: "smooth" });
});

$("#searchToggle").addEventListener("click", toggleSearch);
sortSelect.addEventListener("change", renderProducts);

$("#cartToggle").addEventListener("click", openCart);
$("#cartClose").addEventListener("click", closeCart);
$("#continueShopping").addEventListener("click", closeCart);
$("#overlay").addEventListener("click", closeCart);

$("#cartItems").addEventListener("click", event => {
  const quantityButton = event.target.closest("[data-quantity]");

  if (quantityButton) {
    changeQuantity(
      Number(quantityButton.dataset.id),
      quantityButton.dataset.size,
      Number(quantityButton.dataset.quantity)
    );
    return;
  }

  const removeButton = event.target.closest("[data-remove]");

  if (removeButton) {
    removeCartItem(
      Number(removeButton.dataset.remove),
      removeButton.dataset.size
    );
  }
});

$("#dialogClose").addEventListener("click", closeProductDialog);

$("#productDialog").addEventListener("click", event => {
  if (event.target === $("#productDialog")) closeProductDialog();
});

$("#productDialog").addEventListener("click", event => {
  if (event.target.id === "detailAddButton" && selectedProductId !== null) {
    const size = $("#productSize").value;
    addToCart(selectedProductId, size);
    closeProductDialog();
    openCart();
  }
});

$("#menuToggle").addEventListener("click", () => {
  const nav = $("#mainNav");
  const isOpen = nav.classList.toggle("open");

  $("#menuToggle").setAttribute("aria-expanded", String(isOpen));
  $("#menuToggle").setAttribute(
    "aria-label", isOpen ? "Close navigation" : "Open navigation"
  );
});

$("#mainNav").addEventListener("click", event => {
  if (event.target.closest("a")) closeMobileMenu();
});

document.addEventListener("keydown", event => {
  if (event.key === "Escape") {
    closeMobileMenu();

    if ($("#cartDrawer").classList.contains("open")) closeCart();
  }
});

$("#newsletterForm").addEventListener("submit", event => {
  event.preventDefault();
  $("#newsletterMessage").textContent =
    "Thank you! Newsletter subscriptions need a separate backend setup.";
  $("#newsletterEmail").value = "";
});
$("#checkoutBtn").addEventListener("click", () => {
  if (cart.length === 0) {
    showToast("Your shopping bag is empty.");
    return;
  }

  window.location.href = "checkout.php";
});

$("#currentYear").textContent = new Date().getFullYear();

renderProducts();
renderCart();
