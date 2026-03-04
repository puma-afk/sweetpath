import { apiPost } from "./api.js";
import { validatePhone, getMinDate } from "./ui-utils.js";
import { bindCartCounters } from "./cart-manager.js";
bindCartCounters();

// ---- Advertencia si el cliente intenta salir con el formulario a medias ----
let formSubmitted = false;

function hasFormData() {
  const fields = ['phone', 'name', 'date', 'people', 'flavor', 'theme', 'messageCake', 'qty', 'notes'];
  return fields.some(id => {
    const el = document.getElementById(id);
    return el && el.value.trim() !== '';
  });
}

window.addEventListener('beforeunload', (e) => {
  if (!formSubmitted && hasFormData()) {
    e.preventDefault();
    e.returnValue = '¿Estás seguro de que quieres salir? Tu solicitud aún no fue enviada.';
  }
});

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

// --- Lógica del selector de horario (Pills) ---
document.querySelectorAll('.time-pill').forEach(btn => {
  btn.addEventListener('click', (e) => {
    // Quitar clase activa de todos
    document.querySelectorAll('.time-pill').forEach(b => b.classList.remove('active'));
    // Agregar a este
    e.currentTarget.classList.add('active');
    // Guardar en el input oculto
    document.getElementById('time').value = e.currentTarget.getAttribute('data-val');
  });
});

// --- Lógica de Acordeón "Añadir mensaje..." ---
const toggleMessage = document.getElementById("toggleMessage");
if (toggleMessage) {
  toggleMessage.addEventListener("click", (e) => {
    e.preventDefault();
    const msgInput = document.getElementById("messageCake");
    if (msgInput.style.display === "none") {
      msgInput.style.display = "block";
      msgInput.focus();
      toggleMessage.textContent = "- Ocultar mensaje/referencia";
    } else {
      msgInput.style.display = "none";
      msgInput.value = ""; // Limpiar si lo ocultan
      toggleMessage.textContent = "+ Añadir dedicatoria extra (Opcional)";
    }
  });
}

document.getElementById("btnSend").addEventListener("click", async () => {
  msg.style.display = "block";
  msg.className = "";
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

  const referenceImageInput = document.getElementById("referenceImage");
  const hasImage = referenceImageInput && referenceImageInput.files.length > 0;

  if (!phone || !validatePhone(phone)) {
    msg.textContent = "Por favor ingresa un número de WhatsApp válido (mínimo 8 dígitos).";
    return;
  }
  if (!time) { msg.textContent = "Por favor selecciona un rango horario de retiro."; return; }
  if (!qty) { msg.textContent = "Indica una cantidad."; return; }
  if (!hasImage) { msg.textContent = "Por favor, sube una imagen de referencia."; return; }

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

    // Pedido guardado con éxito — desactivar advertencia de salida
    formSubmitted = true;

    // Subir imagen de referencia
    if (hasImage) {
      try {
        msg.textContent = "Subiendo imagen adjunta...";
        const formData = new FormData();
        formData.append('order_id', data.order_id);
        formData.append('file', referenceImageInput.files[0]);

        const req = await fetch('/api/order_image_upload.php', {
          method: 'POST',
          body: formData
        });
        const res = await req.json();
        if (!req.ok || !res.ok) {
          console.error("Error upload:", res.message);
          throw new Error("No pudimos subir la foto de referencia, pero tu solicitud fue recibida.");
        }
      } catch (uploadErr) {
        msg.textContent = uploadErr.message || "Error al subir la imagen.";
        // Aún así, permitimos que continúe al WhatsApp, porque el formulario principal sí se guardó.
        await new Promise(r => setTimeout(r, 2000));
      }
    }

    if (data.whatsapp_link) {
      msg.textContent = "✅ Solicitud enviada. Abriendo WhatsApp para confirmar con la dueña…";
      // Abrir WhatsApp inmediatamente
      window.open(data.whatsapp_link, '_blank');
      // Redirigir la página actual a una de confirmación si existe, o mostrar mensaje final
      setTimeout(() => {
        msg.textContent = `✅ Solicitud #${data.order_code} guardada. Revisa WhatsApp para continuar.`;
        document.getElementById("btnSend").disabled = true;
      }, 1500);
    } else {
      msg.textContent = `✅ Solicitud #${data.order_code} creada. Falta configurar WhatsApp en admin.`;
    }

  } catch (e) {
    msg.textContent = e.message || "No se pudo enviar el pedido. Intenta de nuevo.";
  }
});