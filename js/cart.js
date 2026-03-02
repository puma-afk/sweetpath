import { apiPost } from "./api.js";

const cartBox = document.getElementById("cartBox");
const msg = document.getElementById("msg");

function getCart() {
  return JSON.parse(localStorage.getItem("sp_cart") || "[]");
}
function setCart(items) {
  localStorage.setItem("sp_cart", JSON.stringify(items));
  render();
}
function render() {
  const cart = getCart();
  if (!cart.length) {
    cartBox.textContent = "Tu carrito está vacío.";
    return;
  }

  cartBox.innerHTML = cart.map((it, idx) => `
    <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;padding:10px 0;border-bottom:1px solid var(--line)">
      <div>
        <b>${it.name}</b><br>
        <small style="color:var(--muted)">Cantidad: ${it.qty}</small>
      </div>
      <div style="display:flex;gap:8px;align-items:center">
        <button class="btn" data-minus="${idx}">-</button>
        <button class="btn" data-plus="${idx}">+</button>
        <button class="btn danger" data-del="${idx}">Quitar</button>
      </div>
    </div>
  `).join("");

  cartBox.querySelectorAll("[data-minus]").forEach(b=>{
    b.addEventListener("click", ()=>{
      const i = Number(b.getAttribute("data-minus"));
      const c = getCart();
      c[i].qty = Math.max(1, c[i].qty - 1);
      setCart(c);
    });
  });
  cartBox.querySelectorAll("[data-plus]").forEach(b=>{
    b.addEventListener("click", ()=>{
      const i = Number(b.getAttribute("data-plus"));
      const c = getCart();
      c[i].qty += 1;
      setCart(c);
    });
  });
  cartBox.querySelectorAll("[data-del]").forEach(b=>{
    b.addEventListener("click", ()=>{
      const i = Number(b.getAttribute("data-del"));
      const c = getCart();
      c.splice(i,1);
      setCart(c);
    });
  });
}

document.getElementById("btnClear").addEventListener("click", ()=>{
  localStorage.removeItem("sp_cart");
  render();
});

document.getElementById("btnConfirm").addEventListener("click", async ()=>{
  msg.style.display = "block";
  msg.textContent = "Procesando…";

  const phone = document.getElementById("phone").value.trim();
  const name = document.getElementById("name").value.trim();
  const date = document.getElementById("date").value.trim();
  const time = document.getElementById("time").value.trim();

  const cart = getCart();
  if (!cart.length) {
    msg.textContent = "Carrito vacío.";
    return;
  }
  if (!phone) {
    msg.textContent = "Tu WhatsApp es obligatorio.";
    return;
  }

  const items = cart.map(it => ({ product_id: it.id, qty: it.qty }));

  try {
    const data = await apiPost("/orders_create.php", {
      type: "EXPRESS",
      customer_name: name,
      customer_phone: phone,
      pickup_date: date,
      pickup_time: time,
      items
    });

    msg.textContent = "Listo ✅ abriendo WhatsApp…";
    // Limpiamos carrito (pedido ya creado)
    localStorage.removeItem("sp_cart");

    if (data.whatsapp_link) {
      window.location.href = data.whatsapp_link;
    } else {
      msg.textContent = "Pedido creado, pero falta configurar WhatsApp en admin/config.";
    }
  } catch (e) {
    // 403 por fuera de horario o pausa
    msg.textContent = e.message || "No se pudo crear el pedido.";
  }
});

render();