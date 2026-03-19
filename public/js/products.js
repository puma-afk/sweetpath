import { apiGet } from "./api.js";
import { addToCart, updateCartMini } from "./cart-manager.js";
import { tarjeta } from "./ui-utils.js?v=2";
import { bindCartBadge } from "./ui.js";
import { bindCartCounters } from "./cart-manager.js";
bindCartCounters();

const grid = document.getElementById("grid");
const meta = document.getElementById("meta");
const pills = document.querySelectorAll(".pill-filtro");

let currentType = "";

// Badge global del carrito (navbar)
bindCartBadge("cartBadge");

async function load() {
  meta.style.display = "block";
  meta.textContent = "Cargando catálogo…";

  try {
    const qs = new URLSearchParams();
    if (currentType) qs.set("type", currentType.toUpperCase());
    qs.set("limit", "150");

    const data = await apiGet(`/products_list.php?${qs.toString()}`);
    const products = data.products || [];

    meta.textContent = `${data.count} producto(s)`;

    if (currentType) {
      // Vista filtrada normal
      grid.innerHTML = `<div class="grilla-productos">${products.map(tarjeta).join("")}</div>`;
    } else {
      // Agrupar por tipo para la vista "Todo"
      const express = products.filter(p => p.type === 'EXPRESS');
      const custom = products.filter(p => p.type === 'CUSTOM');
      const packs = products.filter(p => p.type === 'PACK');

      let html = "";
      if (express.length) {
        html += `<div class="grupo-categoria">
          <h3 class="titulo-categoria"><i class="fas fa-bolt"></i> Listos para llevar (Rápido)</h3>
          <div class="grilla-productos">${express.map(tarjeta).join("")}</div>
        </div>`;
      }
      if (custom.length) {
        html += `<div class="grupo-categoria">
          <h3 class="titulo-categoria"><i class="fas fa-wand-magic-sparkles"></i> Personalizados (Bajo pedido)</h3>
          <div class="grilla-productos">${custom.map(tarjeta).join("")}</div>
        </div>`;
      }
      if (packs.length) {
        html += `<div class="grupo-categoria">
          <h3 class="titulo-categoria"><i class="fas fa-box-open"></i> Packs y Regalos</h3>
          <div class="grilla-productos">${packs.map(tarjeta).join("")}</div>
        </div>`;
      }
      grid.innerHTML = html || `<div class="notice">No hay productos disponibles.</div>`;
    }

    // Actualizar listeners
    attachListeners(products);

  } catch (e) {
    meta.textContent = e?.message || "No se pudo cargar.";
    grid.innerHTML = `<div class="notice">No se pudo cargar el catálogo.</div>`;
  }
}

function attachListeners(products) {
  // bind add buttons
  grid.querySelectorAll("[data-add]").forEach((btn) => {
    btn.addEventListener("click", (e) => {
      e.stopPropagation();
      const id = Number(btn.getAttribute("data-add"));
      const p = products.find((x) => x.id === id);
      if (p) {
        addToCart(p);
        if (window.updateCartMini) window.updateCartMini();
      }
    });
  });

  // bind card click -> modal zoom
  grid.querySelectorAll(".tarjeta").forEach((card) => {
    card.addEventListener("click", (e) => {
      if (e.target.closest('[data-add], a')) return;
      const id = Number(card.dataset.productoId);
      const p = products.find((x) => x.id === id);
      if (p) abrirModalZoom(p, card);
    });
  });

  // global helper for modal's own agregar button
  window.agregarDesdeModal = (id) => {
    const p = products.find((x) => x.id === id);
    if (p) {
      addToCart(p);
      if (window.updateCartMini) window.updateCartMini();
      cerrarModalZoom();
    }
  };
}

function abrirModalZoom(p, card) {
  const modal = document.getElementById('modalFondo');
  
  // Limpiar y preparar estructura zoom
  modal.innerHTML = '<div class="modal-content-zoom" id="modalZoomContent"></div>';
  const content = document.getElementById('modalZoomContent');

  // Calcular origen del zoom desde la tarjeta
  const rect = card.getBoundingClientRect();
  const x = rect.left + rect.width / 2;
  const y = rect.top + rect.height / 2;
  content.style.setProperty('--zoom-origin', `${x}px ${y}px`);

  // Carrusel logic
  const images = [p.image_url, ...(p.gallery_urls || [])].filter(Boolean);
  let carouselHtml = '';
  if (images.length > 1) {
    carouselHtml = `
      <div class="carrusel-detalle">
        <div class="carrusel-pista" id="pista">
          ${images.map(img => `<div class="carrusel-item"><img src="${img}"></div>`).join('')}
        </div>
        <button class="carrusel-btn carrusel-prev" onclick="moverCarrusel(-1)"><i class="fas fa-chevron-left"></i></button>
        <button class="carrusel-btn carrusel-next" onclick="moverCarrusel(1)"><i class="fas fa-chevron-right"></i></button>
        <div class="carrusel-dots" id="dots">
          ${images.map((_, i) => `<div class="dot ${i===0?'activo':''}"></div>`).join('')}
        </div>
      </div>
    `;
  } else {
    carouselHtml = `<div class="carrusel-detalle"><div class="carrusel-item"><img src="${p.image_url}"></div></div>`;
  }

  content.innerHTML = `
    <button class="cerrar-modal" onclick="cerrarModalZoom()" style="z-index:10; position:absolute; top:15px; right:15px; background:rgba(0,0,0,0.5); color:white; border:none; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer;"><i class="fas fa-times"></i></button>
    ${carouselHtml}
    <div style="padding: 24px;">
      <h2 style="color:var(--primario); margin:0 0 8px 0; font-family:'Playfair Display', serif; font-size:1.5rem;">${p.name}</h2>
      <p style="color:#64748b; font-size: 14px; margin-bottom: 20px; line-height:1.4;">${p.description || 'Deliciosa creación artesanal de Esencia.'}</p>
      
      <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #f1f5f9; padding-top:20px;">
        <div>
          <span style="font-weight:900; font-size:1.5rem; color:var(--primario);">Bs ${p.price_bs || (p.price_cents/100).toFixed(2) || '---'}</span>
          <br><small style="color:#94a3b8;">${p.availability === 'OUT' ? 'Agotado' : 'Disponible hoy'}</small>
        </div>
        <button class="boton primario" style="padding: 12px 24px; border-radius:12px;" onclick="agregarDesdeModal(${p.id})">
          <i class="fas fa-cart-plus"></i> Agregar
        </button>
      </div>
    </div>
  `;

  modal.classList.add('activo');
  document.body.style.overflow = 'hidden';

  // Forzar reflow para animación
  setTimeout(() => {
    content.style.transform = "scale(1)";
    content.style.opacity = "1";
  }, 10);

  // Carousel State
  window.carruselIdx = 0;
  window.carruselMax = images.length;
}

window.cerrarModalZoom = () => {
  const modal = document.getElementById('modalFondo');
  modal.classList.remove('activo');
  document.body.style.overflow = '';
};

window.moverCarrusel = (dir) => {
  const pista = document.getElementById('pista');
  const dots = document.querySelectorAll('.dot');
  if (!pista) return;
  window.carruselIdx = (window.carruselIdx + dir + window.carruselMax) % window.carruselMax;
  pista.style.transform = `translateX(-${window.carruselIdx * 100}%)`;
  dots.forEach((d, i) => d.classList.toggle('activo', i === window.carruselIdx));
};

// Logic for Pills
pills.forEach(pill => {
  pill.addEventListener("click", () => {
    pills.forEach(p => p.classList.remove("activo"));
    pill.classList.add("activo");
    currentType = pill.getAttribute("data-type") || "";
    load();
  });
});

// Permitir type desde URL: products.html?type=EXPRESS
const params = new URLSearchParams(location.search);
const initialType = (params.get("type") || "").toUpperCase();
if (initialType) {
  const matchingPill = Array.from(pills).find(p => p.getAttribute("data-type") === initialType);
  if (matchingPill) {
    pills.forEach(p => p.classList.remove("activo"));
    matchingPill.classList.add("activo");
    currentType = initialType;
  }
}

// Inicialización
if (window.updateCartMini) window.updateCartMini();
load();