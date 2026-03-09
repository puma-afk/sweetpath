import { apiPost } from "./api.js";
import { validatePhone, getMinDate } from "./ui-utils.js";
import { bindCartCounters, clearCart } from "./cart-manager.js";
bindCartCounters();

// ---- Fin de configuración de advertencia (Removida por ser intrusiva) ----

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
    let formSubmitted = true;
    clearCart();

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

    if (data && data.whatsapp_link) {
      msg.textContent = `✅ Solicitud #${data.order_code} enviada. Abriendo WhatsApp…`;
      window.open(data.whatsapp_link, '_blank');
      setTimeout(() => {
        msg.textContent = `✅ Solicitud #${data.order_code} guardada. Redirigiendo a tus pedidos...`;
        document.getElementById("btnConfirm").disabled = true;
        window.location.href = './mis-pedidos.html';
      }, 2000);
    } else {
      msg.textContent = `✅ Solicitud #${data.order_code} creada. Redirigiendo...`;
      setTimeout(() => {
        window.location.href = './mis-pedidos.html';
      }, 2000);
    }

  } catch (e) {
    msg.textContent = e.message || "No se pudo enviar el pedido. Intenta de nuevo.";
  }
});