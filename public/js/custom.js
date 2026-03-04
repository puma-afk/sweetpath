import { apiPost } from "./api.js";
import { validatePhone, getMinDate } from "./ui-utils.js";
import { bindCartCounters } from "./cart-manager.js";
bindCartCounters();

const msg = document.getElementById("msg");
const typeEl = document.getElementById("type");
const dateEl = document.getElementById("date");

function qp(name) {
  return new URLSearchParams(location.search).get(name);
}

function updateMinDate() {
  if (dateEl && typeEl) {
    dateEl.min = getMinDate(typeEl.value);
  }
}

// si viene ?type=PACK o CUSTOM desde catálogo
const typeFromUrl = (qp("type") || "").toUpperCase();
if (typeFromUrl === "PACK" || typeFromUrl === "CUSTOM") {
  if (typeEl) typeEl.value = typeFromUrl;
}

if (typeEl) {
  typeEl.addEventListener("change", updateMinDate);
}
updateMinDate();

document.getElementById("btnSend").addEventListener("click", async ()=>{
  msg.style.display = "block";
  msg.textContent = "Procesando…";

  const type = document.getElementById("type").value.trim();
  const phone = document.getElementById("phone").value.trim();
  const name = document.getElementById("name").value.trim();
  const date = document.getElementById("date").value.trim();
  const time = document.getElementById("time").value.trim();

  const people = document.getElementById("people").value.trim();
  const flavor = document.getElementById("flavor").value.trim();
  const size = document.getElementById("size").value.trim();
  const theme = document.getElementById("theme").value.trim();
  const messageCake = document.getElementById("messageCake").value.trim();
  const qty = document.getElementById("qty").value.trim();
  const notes = document.getElementById("notes").value.trim();

  if (!phone || !validatePhone(phone)) { 
    msg.textContent = "Por favor ingresa un número de WhatsApp válido (mínimo 8 dígitos)."; 
    return; 
  }
  if (!qty) { msg.textContent = "Indica una cantidad."; return; }

  const details = {
    people: people ? Number(people) : null,
    flavor: flavor || null,
    size: size || null,
    theme: theme || null,
    message: messageCake || null,
    qty: Number(qty),
    notes: notes || null,
  };

  try {
    const data = await apiPost("/orders_request.php", {
      type,
      customer_name: name,
      customer_phone: phone,
      pickup_date: date,
      pickup_time: time,
      details
    });

    msg.textContent = "Solicitud enviada ✅ abriendo WhatsApp…";
    if (data.whatsapp_link) window.location.href = data.whatsapp_link;
    else msg.textContent = "Solicitud creada, pero falta configurar WhatsApp en admin/config.";
  } catch (e) {
    msg.textContent = e.message || "No se pudo enviar la solicitud.";
  }
});