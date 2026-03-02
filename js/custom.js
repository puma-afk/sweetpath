import { apiPost } from "./api.js";

const msg = document.getElementById("msg");

document.getElementById("btnSend").addEventListener("click", async ()=>{
  msg.style.display = "block";
  msg.textContent = "Procesando…";

  const type = document.getElementById("type").value.trim();
  const phone = document.getElementById("phone").value.trim();
  const name = document.getElementById("name").value.trim();
  const date = document.getElementById("date").value.trim();
  const time = document.getElementById("time").value.trim();
  const details = document.getElementById("details").value.trim();

  if (!phone) { msg.textContent = "Tu WhatsApp es obligatorio."; return; }
  if (!details) { msg.textContent = "Describe tu pedido (detalles)."; return; }

  // Adaptamos a tu backend (orders_request.php ya construye el WhatsApp link)
  // Puedes guardar los detalles en custom_json.
  const payload = {
    type, // CUSTOM o PACK
    customer_name: name,
    customer_phone: phone,
    pickup_date: date,
    pickup_time: time,
    custom_json: {
      details
    }
  };

  try {
    const data = await apiPost("/orders_request.php", payload);
    msg.textContent = "Solicitud enviada ✅ abriendo WhatsApp…";
    if (data.whatsapp_link) window.location.href = data.whatsapp_link;
    else msg.textContent = "Solicitud creada, pero falta WhatsApp en admin/config.";
  } catch (e) {
    msg.textContent = e.message || "No se pudo enviar la solicitud.";
  }
});