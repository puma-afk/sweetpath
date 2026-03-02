
export function getCart() {
  return JSON.parse(localStorage.getItem("sp_cart") || "[]");
}

export function setCart(items) {
  localStorage.setItem("sp_cart", JSON.stringify(items));
  updateCartMini();
}

export function addToCart(p) {
  const cart = getCart();
  const idx = cart.findIndex(x => x.id === p.id);
  if (idx >= 0) cart[idx].qty += 1;
  else cart.push({ id: p.id, name: p.name, price_bs: p.price_bs, qty: 1 });
  setCart(cart);
}

export function clearCart() {
  localStorage.removeItem("sp_cart");
  updateCartMini();
}

export function updateCartMini() {
  const cartMini = document.getElementById("cartMini");
  if (!cartMini) return;
  const cart = getCart();
  const total = cart.reduce((s, it) => s + (it.qty || 0), 0);
  cartMini.textContent = `Carrito: ${total}`;
}
