import { apiGet } from "./api.js";
import { addToCart, updateCartMini } from "./cart-manager.js";
import { card } from "./ui-utils.js";
import { bindCartBadge, initializeNav } from "./ui.js";
import { bindCartCounters } from "./cart-manager.js";
bindCartCounters();
initializeNav();

const grid = document.getElementById("grid");
const meta = document.getElementById("meta");
const qEl = document.getElementById("q");
const typeEl = document.getElementById("type");

// Badge global del carrito (navbar)
bindCartBadge("cartBadge");

async function load() {
  meta.style.display = "block";
  meta.textContent = "Cargando catálogo…";

  try {
    const q = qEl.value.trim();
    const type = typeEl.value.trim();

    const qs = new URLSearchParams();
    if (type) qs.set("type", type.toUpperCase());
    if (q) qs.set("q", q);
    qs.set("limit", "120");

    const data = await apiGet(`/products_list.php?${qs.toString()}`);

    meta.textContent = `${data.count} producto(s)`;
    grid.innerHTML = (data.products || []).map(card).join("");

    // bind add buttons
    grid.querySelectorAll("[data-add]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const id = Number(btn.getAttribute("data-add"));
        const p = (data.products || []).find((x) => x.id === id);
        if (p) {
          addToCart(p);       // debe guardar en localStorage
          updateCartMini();   // tu contador local (si todavía lo usas)
          // bindCartBadge se actualiza solo si cart-manager dispara el evento o escribe en localStorage
        }
      });
    });
  } catch (e) {
    meta.textContent = e?.message || "No se pudo cargar.";
    grid.innerHTML = `<div class="notice">No se pudo cargar el catálogo.</div>`;
  }
}

document.getElementById("btnSearch").addEventListener("click", load);
document.getElementById("btnClear").addEventListener("click", () => {
  qEl.value = "";
  typeEl.value = "";
  load();
});

// Permitir type desde URL: products.html?type=EXPRESS
const params = new URLSearchParams(location.search);
const initialType = (params.get("type") || "").toUpperCase();
if (["EXPRESS", "CUSTOM", "PACK"].includes(initialType)) {
  typeEl.value = initialType;
}

// Inicialización
updateCartMini();
load();