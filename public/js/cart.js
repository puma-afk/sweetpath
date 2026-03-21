import { apiPost } from "./api.js";
import { getCart, setCart, clearCart, bindCartCounters } from "./cart-manager.js";
import { validatePhone, getMinDate } from "./ui-utils.js";

bindCartCounters();

// ---- Fin de configuración de advertencia (Removida por ser intrusiva) ----

const cartBox = document.getElementById("cartBox");
const msg = document.getElementById("msg");
const dateEl = document.getElementById("date");

// Set min date depending on cart contents (if mixed packs, then 24 hours)
function updateMinDateForCart() {
  if (!dateEl) return;
  const cart = getCart();
  // We need to know if any item is a PACK, but cart only has IDs. 
  // For now, let's keep the backend valid date logic or default to today + check in backend.
  dateEl.min = getMinDate("EXPRESS");
}
updateMinDateForCart();

// --- Lógica del selector de horario (Pills) ---
document.querySelectorAll('.time-pill').forEach(btn => {
  btn.addEventListener('click', (e) => {
    document.querySelectorAll('.time-pill').forEach(b => b.classList.remove('active'));
    e.currentTarget.classList.add('active');
    document.getElementById('time').value = e.currentTarget.getAttribute('data-val');
  });
});

// --- Lógica de Acordeón "Añadir mensaje..." ---
const toggleMessage = document.getElementById("toggleMessage");
if (toggleMessage) {
  toggleMessage.addEventListener("click", (e) => {
    e.preventDefault();
    const msgInput = document.getElementById("customerNote");
    if (msgInput.style.display === "none") {
      msgInput.style.display = "block";
      msgInput.focus();
      toggleMessage.textContent = "- Ocultar mensaje, indicación o nota";
    } else {
      msgInput.style.display = "none";
      msgInput.value = "";
      toggleMessage.textContent = "+ Añadir mensaje, indicación o nota al pedido (Opcional)";
    }
  });
}

function render() {
  const cart = getCart();

  if (!cart.length) {
    cartBox.textContent = "Tu carrito está vacío.";
    const totalContainer = document.getElementById("cartTotalContainer");
    if (totalContainer) totalContainer.style.display = "none";
    return;
  }

  cartBox.innerHTML = cart
    .map(
      (it, idx) => `
    <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;padding:10px 0;border-bottom:1px solid var(--line)">
      <div>
        <b>${it.name}</b><br>
        <small style="color:var(--muted)">Cantidad: ${it.qty}</small>
      </div>
      <div style="display:flex;gap:8px;align-items:center">
        <button class="btn" style="min-height: 44px" data-minus="${idx}">-</button>
        <button class="btn" style="min-height: 44px" data-plus="${idx}">+</button>
        <button class="btn danger" style="min-height: 44px" data-del="${idx}">Eliminar</button>
      </div>
    </div>
  `
    )
    .join("");

  cartBox.querySelectorAll("[data-minus]").forEach((b) => {
    b.addEventListener("click", () => {
      const i = Number(b.getAttribute("data-minus"));
      const c = getCart();
      c[i].qty = Math.max(1, (Number(c[i].qty) || 1) - 1);
      setCart(c);
      render();
    });
  });

  cartBox.querySelectorAll("[data-plus]").forEach((b) => {
    b.addEventListener("click", () => {
      const i = Number(b.getAttribute("data-plus"));
      const c = getCart();
      c[i].qty = (Number(c[i].qty) || 0) + 1;
      setCart(c);
      render();
    });
  });

  cartBox.querySelectorAll("[data-del]").forEach((b) => {
    b.addEventListener("click", () => {
      const i = Number(b.getAttribute("data-del"));
      const c = getCart();
      c.splice(i, 1);
      setCart(c);
      render();
    });
  });

  // Calculate and display total
  let totalBs = 0;
  let allPriced = true;
  cart.forEach(it => {
    if (it.price_bs) {
      totalBs += (Number(it.price_bs) * Number(it.qty));
    } else {
      allPriced = false; // Si hay items personalizados o sin precio
    }
  });

  const totalContainer = document.getElementById("cartTotalContainer");
  const totalText = document.getElementById("cartTotalText");

  if (totalContainer && totalText) {
    if (totalBs > 0) {
      totalContainer.style.display = "block";
      totalText.textContent = totalBs.toFixed(2) + (allPriced ? "" : " + a cotizar");
    } else {
      totalContainer.style.display = "none";
    }
  }
}

document.getElementById("btnClear")?.addEventListener("click", () => {
  clearCart();
  render();
});

document.getElementById("btnConfirm")?.addEventListener("click", async () => {
  msg.style.display = "block";
  msg.textContent = "Procesando…";

  const phone = document.getElementById("phone").value.trim();
  const name = document.getElementById("name").value.trim();
  const date = document.getElementById("date").value.trim();
  const time = document.getElementById("time").value.trim();
  const note = (document.getElementById("customerNote")?.value || "").trim();
  const paymentMethod = document.getElementById("paymentMethod").value;

  const cart = getCart();
  if (!cart.length) {
    msg.textContent = "Carrito vacío.";
    return;
  }

  if (!phone || !validatePhone(phone)) {
    msg.textContent = "Por favor ingresa un número de WhatsApp válido (mínimo 8 dígitos).";
    return;
  }
  if (!time || !date) {
    msg.textContent = "Por favor selecciona una fecha y un horario para el retiro.";
    return;
  }

  const items = cart.map((it) => ({ product_id: it.id, qty: it.qty }));

  try {
    const data = await apiPost("/orders_create.php", {
      type: "EXPRESS",
      customer_name: name,
      customer_phone: phone,
      pickup_date: date,
      pickup_time: time,
      customer_note: note,
      payment_method: paymentMethod,
      items,
    });

    clearCart();

    if (data && data.whatsapp_link) {
      msg.textContent = `✅ Pedido #${data.order_code} creado. Abriendo WhatsApp…`;
      window.open(data.whatsapp_link, '_blank');
      setTimeout(() => {
        msg.textContent = `✅ Pedido #${data.order_code} guardado. Redirigiendo a tus pedidos...`;
        document.getElementById("btnConfirm").disabled = true;
        window.location.href = './mis-pedidos.html';
      }, 2000);
    } else {
      msg.textContent = `✅ Pedido #${data.order_code} creado. Redirigiendo...`;
      setTimeout(() => {
        window.location.href = './mis-pedidos.html';
      }, 2000);
    }
  } catch (e) {
    msg.textContent = e?.message || "No se pudo crear el pedido.";
  }
});

render();