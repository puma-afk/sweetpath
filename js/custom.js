import { apiPost } from "./api.js";

const msg = document.getElementById("msg");

function qp(name) {
  return new URLSearchParams(location.search).get(name);
}

// si viene ?type=PACK o CUSTOM desde catálogo
const typeFromUrl = (qp("type") || "").toUpperCase();
if (typeFromUrl === "PACK" || typeFromUrl === "CUSTOM") {
  const typeEl = document.getElementById("type");
  if (typeEl) typeEl.value = typeFromUrl;
}

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

  if (!phone) { msg.textContent = "Tu WhatsApp es obligatorio."; return; }
  if (!qty) { msg.textContent = "Indica una cantidad."; return; }

  const custom_json = {
    people: people ? Number(people) : null,
    flavor: flavor || null,
    size: size || null,
    theme: theme || null,
    message: messageCake || null,
    qty: Number(qty),
    notes: notes || null,
  };

  // texto resumen (por si el backend arma WhatsApp desde json)
  custom_json.summary =
    `Tipo: ${type}\n` +
    (date ? `Recojo: ${date}${time ? " "+time : ""}\n` : "") +
    (people ? `Personas: ${people}\n` : "") +
    `Cantidad: ${qty}\n` +
    (flavor ? `Sabor: ${flavor}\n` : "") +
    (size ? `Tamaño: ${size}\n` : "") +
    (theme ? `Tema: ${theme}\n` : "") +
    (messageCake ? `Mensaje: ${messageCake}\n` : "") +
    (notes ? `Notas: ${notes}\n` : "");

  try {
    const data = await apiPost("/orders_request.php", {
      type,
      customer_name: name,
      customer_phone: phone,
      pickup_date: date,
      pickup_time: time,
      custom_json
    });

    msg.textContent = "Solicitud enviada ✅ abriendo WhatsApp…";
    if (data.whatsapp_link) window.location.href = data.whatsapp_link;
    else msg.textContent = "Solicitud creada, pero falta configurar WhatsApp en admin/config.";
  } catch (e) {
    msg.textContent = e.message || "No se pudo enviar la solicitud.";
  }
});