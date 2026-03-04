// public/js/ui.js
export function getCart() {
  return JSON.parse(localStorage.getItem("sp_cart") || "[]");
}

export function cartCount() {
  return getCart().reduce((s, it) => s + (Number(it.qty) || 0), 0);
}

export function setCart(items) {
  localStorage.setItem("sp_cart", JSON.stringify(items));
  notifyCartUpdated();
}

export function notifyCartUpdated() {
  // dispara evento local para actualizar contadores
  window.dispatchEvent(new Event("sp_cart_updated"));
}

export function bindCartBadge(badgeId = "cartBadge") {
  const el = document.getElementById(badgeId);
  if (!el) return;

  const render = () => {
    const n = cartCount();
    el.textContent = `Carrito (${n})`;
  };

  render();
  window.addEventListener("sp_cart_updated", render);

  // por si abres otra pestaña
  window.addEventListener("storage", (e) => {
    if (e.key === "sp_cart") render();
  });
}

// Mensajitos bonitos según error común
export function prettyError(err) {
  const msg = (err?.message || "").toLowerCase();

  // Horario / pausa
  if (err?.status === 403) {
    return "Ahora mismo los pedidos EXPRESS están pausados 🙏\nPuedes intentar más tarde o enviar una solicitud PERSONALIZADA/PACK.";
  }
  if (msg.includes("fuera de horario") || msg.includes("pausad")) {
    return "Estamos fuera de horario por ahora 🌙\nPuedes enviar solicitud CUSTOM/PACK y te confirmamos cuando estemos atendiendo.";
  }

  // Stock / cantidad
  if (msg.includes("cantidad no disponible") || msg.includes("reduce la cantidad")) {
    return "Uy 😅 parece que esa cantidad no está disponible.\nReduce la cantidad y confirmamos por WhatsApp antes del pago.";
  }

  // WhatsApp config
  if (msg.includes("config") || msg.includes("whatsapp")) {
    return "Falta configurar el WhatsApp de la tienda en Admin → Config ⚙️";
  }

  return err?.message || "Ocurrió un error. Intenta de nuevo 🙏";
}

export function showNotice(id, text) {
  const el = document.getElementById(id);
  if (!el) return;
  el.style.display = "block";
  el.textContent = text;
}