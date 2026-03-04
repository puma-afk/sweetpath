// js/cart-manager.js

export function getCart() {
  return JSON.parse(localStorage.getItem("sp_cart") || "[]");
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
  localStorage.setItem("sp_cart", JSON.stringify(items));
  notifyCartUpdated();
  updateCartMini(); // si existe en la página
  updateCartBadge(); // si existe en navbar
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
  setCart([]);
}

/** Mini contador local (si tu página lo tiene) */
export function updateCartMini() {
  const cartMini = document.getElementById("cartMini");
  if (!cartMini) return;

  const total = cartCount();
  cartMini.textContent = `Carrito: ${total}`;
}

/** Badge global en navbar (si existe) */
export function updateCartBadge() {
  const badge = document.getElementById("cartBadge");
  if (!badge) return;

  const total = cartCount();
  badge.textContent = `Carrito (${total})`;
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
    if (e.key === "sp_cart") render();
  });
}