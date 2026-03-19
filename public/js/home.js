import { apiGet } from "./api.js";
import { tarjeta } from "./ui-utils.js?v=2";
import { bindCartCounters, addToCart } from "./cart-manager.js";
bindCartCounters();

const promoMeta = document.getElementById("promoMeta");
const promoBanner = document.getElementById("promoBanner");
const productsGrid = document.getElementById("productsGrid");
const recMeta = document.getElementById("recMeta");

async function loadPromos() {
  try {
    const data = await apiGet("/promos_list.php");
    promoMeta.textContent = data.count ? `${data.count} activa(s)` : "Sin promos";
    if (data.promos?.length) {
      const p = data.promos[0];
      promoBanner.innerHTML = `<img src="${p.image_url}" alt="${p.title}">`;
    } else {
      promoBanner.innerHTML = `<small style="color:var(--muted)">Sin promos por ahora</small>`;
    }
  } catch {
    promoMeta.textContent = "No disponible";
  }
}

async function loadRecommended() {
  try {
    const data = await apiGet("/products_list.php?limit=12");
    recMeta.textContent = `${data.count} producto(s)`;
    const products = data.products || [];
    
    productsGrid.className = "grilla-productos"; // Cambiar de scroll-horizontal a grilla
    productsGrid.innerHTML = products.slice(0, 12).map(tarjeta).join("");

    // bind card click -> modal zoom
    productsGrid.querySelectorAll(".tarjeta").forEach((card) => {
      card.addEventListener("click", (e) => {
        if (e.target.closest('[data-add], a')) return;
        const id = Number(card.dataset.productoId);
        const p = products.find((x) => x.id === id);
        if (p) abrirModalZoom(p, card);
      });
    });

    // bind add buttons
    productsGrid.querySelectorAll("[data-add]").forEach((btn) => {
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

  } catch (e) {
    recMeta.textContent = "Error cargando";
    productsGrid.innerHTML = `<div class="notice">No se pudo cargar el catálogo.</div>`;
  }
}

// Unificar Modal Zoom en Inicio
function abrirModalZoom(p, card) {
    const modal = document.getElementById('modalFondo');
    modal.innerHTML = '<div class="modal-content-zoom" id="modalZoomContent"></div>';
    const content = document.getElementById('modalZoomContent');

    // Calcular origen del zoom desde la tarjeta
    const rect = card.getBoundingClientRect();
    const x = rect.left + rect.width / 2;
    const y = rect.top + rect.height / 2;
    content.style.setProperty('--zoom-origin', `${x}px ${y}px`);

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
        <p style="color:#64748b; font-size: 14px; margin-bottom: 20px; line-height:1.4;">${p.description || 'Artesanía pura de Esencia.'}</p>
        <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #f1f5f9; padding-top:20px;">
          <div>
            <span style="font-weight:900; font-size:1.5rem; color:var(--primario);">Bs ${p.price_bs || (p.price_cents/100).toFixed(2) || '---'}</span>
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

window.agregarDesdeModal = (id) => {
    // La lógica de agregar al carrito ya está en cart-manager y se maneja por delegación o helper
    // Como estamos en Inicio, necesitamos re-buscar el producto o usar cart-manager directamente
    apiGet("/products_list.php").then(data => {
        const p = (data.products || []).find(x => x.id === id);
        if (p) {
            addToCart(p);
            if (window.updateCartMini) window.updateCartMini();
            cerrarModalZoom();
        }
    });
};

async function loadStoreStatus() {
  const storeStatusContainer = document.getElementById("storeStatusContainer");
  if (!storeStatusContainer) return;
  try {
    const res = await fetch("/sweetpath/api/store_status.php");
    const status = await res.json();
    if (status.is_open) {
      storeStatusContainer.innerHTML = `<span style="background: rgba(46, 204, 113, 0.15); color: #2ecc71; border: 1px solid rgba(46, 204, 113, 0.5); padding: 6px 14px; border-radius: 20px; font-weight: 700; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 8px; backdrop-filter: blur(4px); letter-spacing: 0.5px; text-transform: uppercase;"><i class="fas fa-door-open"></i> Abierto Ahora</span>`;
    } else {
      storeStatusContainer.innerHTML = `<span style="background: rgba(231, 76, 60, 0.15); color: #e74c3c; border: 1px solid rgba(231, 76, 60, 0.5); padding: 6px 14px; border-radius: 20px; font-weight: 700; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 8px; backdrop-filter: blur(4px); letter-spacing: 0.5px; text-transform: uppercase;"><i class="fas fa-door-closed"></i> Cerrado</span>`;
    }
  } catch (e) {
    storeStatusContainer.innerHTML = "";
  }
}

loadPromos();
loadRecommended();
loadStoreStatus();