import { apiGet } from "./api.js";
import { addToCart, updateCartMini } from "./cart-manager.js";
import { card } from "./ui-utils.js";

const grid = document.getElementById("grid");
const meta = document.getElementById("meta");
const qEl = document.getElementById("q");
const typeEl = document.getElementById("type");

async function load() {
  meta.style.display = "block";
  meta.textContent = "Cargando catálogo…";

  try {
    const q = encodeURIComponent(qEl.value.trim());
    const type = typeEl.value.trim();
    const qs = [];
    if (type) qs.push(`type=${encodeURIComponent(type)}`);
    if (q) qs.push(`q=${q}`);
    qs.push("limit=120");

    const data = await apiGet(`/products_list.php?${qs.join("&")}`);
    meta.textContent = `${data.count} producto(s)`;
    grid.innerHTML = (data.products || []).map(card).join("");

    // bind add buttons
    grid.querySelectorAll("[data-add]").forEach(btn => {
      btn.addEventListener("click", () => {
        const id = Number(btn.getAttribute("data-add"));
        const p = (data.products || []).find(x => x.id === id);
        if (p) addToCart(p);
      });
    });
  } catch (e) {
    meta.textContent = e.message || "No se pudo cargar.";
    grid.innerHTML = "";
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
const initialType = params.get("type");
if (initialType) typeEl.value = initialType.toUpperCase();

updateCartMini();
load();