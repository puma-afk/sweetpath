// js/cart-manager.js

const CART_KEY = "sp_cart";
const CART_TS_KEY = "sp_cart_ts";
const CART_TTL_MS = 4 * 60 * 60 * 1000; // 4 horas

function isCartExpired() {
  const ts = localStorage.getItem(CART_TS_KEY);
  if (!ts) return false; // No timestamp = no cart yet
  return (Date.now() - Number(ts)) > CART_TTL_MS;
}

function touchCartTimestamp() {
  localStorage.setItem(CART_TS_KEY, String(Date.now()));
}

export function getCart() {
  if (isCartExpired()) {
    localStorage.removeItem(CART_KEY);
    localStorage.removeItem(CART_TS_KEY);
    return [];
  }
  return JSON.parse(localStorage.getItem(CART_KEY) || "[]");
}

export function cartCount() {
  const cart = getCart();
  return cart.reduce((s, it) => s + (Number(it.qty) || 0), 0);
}

function notifyCartUpdated() {
  // Actualiza contadores en esta pestaña
  window.dispatchEvent(new Event("sp_cart_updated"));
}

export function setCart(items) {
  localStorage.setItem(CART_KEY, JSON.stringify(items));
  touchCartTimestamp();
  notifyCartUpdated();
  updateCartMini();
  updateCartBadge();
}

export function addToCart(p) {
  const cart = getCart();
  const idx = cart.findIndex(x => x.id === p.id);

  if (idx >= 0) {
    cart[idx].qty = (Number(cart[idx].qty) || 0) + 1;
  } else {
    cart.push({
      id: p.id,
      name: p.name,
      price_bs: p.price_bs ?? null,
      qty: 1
    });
  }

  setCart(cart);
}

export function removeFromCart(productId) {
  const cart = getCart().filter(it => it.id !== productId);
  setCart(cart);
}

export function updateQty(productId, qty) {
  qty = Number(qty) || 1;
  if (qty < 1) qty = 1;

  const cart = getCart();
  const idx = cart.findIndex(it => it.id === productId);
  if (idx >= 0) {
    cart[idx].qty = qty;
    setCart(cart);
  }
}

export function clearCart() {
  localStorage.removeItem(CART_KEY);
  localStorage.removeItem(CART_TS_KEY);
  setCart([]);
}

/** Mini contador local (si tu página lo tiene) */
export function updateCartMini() {
  const cartMini = document.getElementById("cartMini");
  if (!cartMini) return;

  const total = cartCount();
  cartMini.textContent = `Carrito: ${total}`;
}

/** Badge global en navbar y flotante */
export function updateCartBadge() {
  const badge = document.getElementById("cartBadge");
  const floatCart = document.getElementById("floatingCart");
  const floatBadge = document.getElementById("floatingCartBadge");
  const total = cartCount();

  if (badge) {
    badge.textContent = `Mi pedido (${total})`;
  }

  if (floatCart && floatBadge) {
    if (total > 0) {
      floatCart.style.display = "flex";

      // Animación si el valor cambia
      if (floatBadge.textContent !== String(total)) {
        floatCart.classList.remove("pop");
        void floatCart.offsetWidth; // trigger reflow
        floatCart.classList.add("pop");
      }
      floatBadge.textContent = total;
    } else {
      floatCart.style.display = "none";
    }
  }
}

/**
 * Llamar una vez por página para:
 * - renderizar contadores al cargar
 * - escuchar cambios por evento y por "storage" (otras pestañas)
 */
export function bindCartCounters() {
  const render = () => {
    updateCartMini();
    updateCartBadge();
  };

  render();
  window.addEventListener("sp_cart_updated", render);
  window.addEventListener("storage", (e) => {
    if (e.key === CART_KEY) render();
  });
}