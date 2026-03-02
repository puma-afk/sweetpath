import { apiGet } from "./api.js";

const grid = document.getElementById("grid");
const meta = document.getElementById("meta");
const qEl = document.getElementById("q");
const typeEl = document.getElementById("type");
const cartMini = document.getElementById("cartMini");

function getCart() {
  return JSON.parse(localStorage.getItem("sp_cart") || "[]");
}
function setCart(items) {
  localStorage.setItem("sp_cart", JSON.stringify(items));
  updateCartMini();
}
function updateCartMini() {
  const cart = getCart();
  const total = cart.reduce((s, it) => s + (it.qty || 0), 0);
  cartMini.textContent = `Carrito: ${total}`;
}
function addToCart(p) {
  const cart = getCart();
  const idx = cart.findIndex(x => x.id === p.id);
  if (idx >= 0) cart[idx].qty += 1;
  else cart.push({ id: p.id, name: p.name, price_bs: p.price_bs, qty: 1 });
  setCart(cart);
}

function tagClass(av) {
  if (av === "OUT") return "out";
  if (av === "LOW") return "low";
  return "";
}

function card(p) {
  const img = p.image_url ? `<img src="${p.image_url}" alt="">` : "";
  const price = p.price_bs ? `Bs ${p.price_bs}` : "Precio a confirmar";
  const desc = (p.description || "").slice(0, 90);

  const canAdd = p.type === "EXPRESS" && p.availability !== "OUT";

  return `
    <div class="card">
      <div class="cardImg">${img}</div>
      <div class="cardBody">
        <h4 class="cardTitle">${p.name}</h4>
        <p class="cardDesc">${desc}${desc.length>=90?'…':''}</p>
        <div class="meta">
          <div>
            <div class="price">${price}</div>
            <div class="tag ${tagClass(p.availability)}" style="margin-top:8px">${p.type} • ${p.availability}</div>
          </div>
          <div>
            ${
              canAdd
                ? `<button class="btn primary" data-add="${p.id}">Agregar</button>`
                : (p.type === "EXPRESS"
                    ? `<button class="btn" disabled>No disponible</button>`
                    : `<a class="btn" href="./custom.html">Solicitar</a>`)
            }
          </div>
        </div>
      </div>
    </div>
  `;
}

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