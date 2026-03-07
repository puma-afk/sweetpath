import { apiGet } from "./api.js";
import { card } from "./ui-utils.js";
import { bindCartCounters, addToCart } from "./cart-manager.js";
import { initializeNav } from "./ui.js";
bindCartCounters();
initializeNav();

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
    // Traemos EXPRESS primero (por tu ordenamiento en API)
    const data = await apiGet("/products_list.php?limit=6");
    recMeta.textContent = `${data.count} producto(s)`;
    productsGrid.innerHTML = (data.products || []).slice(0, 6).map(card).join("");

    // Bindear botones agregar al carrito del index
    productsGrid.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-add]");
      if (!btn) return;

      const id = btn.getAttribute("data-add");
      const product = data.products.find((p) => String(p.id) === id);
      if (product) {
        addToCart(product);
      }
    });

  } catch (e) {
    recMeta.textContent = "Error cargando";
    productsGrid.innerHTML = `<div class="notice">No se pudo cargar el catálogo.</div>`;
  }
}

loadPromos();
loadRecommended();