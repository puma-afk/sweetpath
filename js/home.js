import { apiGet } from "./api.js";

const promoMeta = document.getElementById("promoMeta");
const promoBanner = document.getElementById("promoBanner");
const productsGrid = document.getElementById("productsGrid");
const recMeta = document.getElementById("recMeta");

function card(p) {
  const tagClass = p.availability === "OUT" ? "out" : (p.availability === "LOW" ? "low" : "");
  const price = p.price_bs ? `Bs ${p.price_bs}` : "Precio a confirmar";
  const img = p.image_url ? `<img src="${p.image_url}" alt="">` : "";
  const desc = (p.description || "").slice(0, 90);

  return `
    <div class="card">
      <div class="cardImg">${img}</div>
      <div class="cardBody">
        <h4 class="cardTitle">${p.name}</h4>
        <p class="cardDesc">${desc}${desc.length>=90?'…':''}</p>
        <div class="meta">
          <div class="price">${price}</div>
          <div class="tag ${tagClass}">${p.availability}</div>
        </div>
      </div>
    </div>
  `;
}

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
  } catch (e) {
    recMeta.textContent = "Error cargando";
    productsGrid.innerHTML = `<div class="notice">No se pudo cargar el catálogo.</div>`;
  }
}

loadPromos();
loadRecommended();