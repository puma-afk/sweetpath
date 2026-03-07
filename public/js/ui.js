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

import { bindCartCounters } from "./cart-manager.js";

export function bindCartBadge() {
  bindCartCounters();
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

/**
 * Inicializa el menú responsivo (hamburguesa)
 */
export function initializeNav() {
  const toggle = document.querySelector(".menu-toggle");
  const navlinks = document.querySelector(".navlinks");
  
  if (toggle && navlinks) {
    toggle.addEventListener("click", (e) => {
      e.stopPropagation();
      navlinks.classList.toggle("active");
      toggle.textContent = navlinks.classList.contains("active") ? "✕" : "☰";
    });

    // Cerrar al hacer click fuera
    document.addEventListener("click", (e) => {
      if (!navlinks.contains(e.target) && !toggle.contains(e.target)) {
        navlinks.classList.remove("active");
        toggle.textContent = "☰";
      }
    });

    // Cerrar al redimensionar si es PC
    window.addEventListener("resize", () => {
      if (window.innerWidth > 800) {
        navlinks.classList.remove("active");
        toggle.textContent = "☰";
      }
    });
  }
}