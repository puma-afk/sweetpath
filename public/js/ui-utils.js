
export function card(p) {
  const img = p.image_url ? `<img src="${p.image_url}" alt="">` : "";
  const priceMain = p.price_bs ? `Bs ${p.price_bs}` : "Precio a confirmar";
  const priceSub = p.price_bs ? "precio referencial" : "se cotiza por WhatsApp";
  const desc = (p.description || "").slice(0, 92);

  const typeBadge =
    p.type === "EXPRESS" ? "express" :
    p.type === "CUSTOM" ? "custom" : "pack";

  const outBadge = p.availability === "OUT" ? " out" : "";
  const badgeText = `${p.type} • ${p.availability}`;

  const canAdd = p.type === "EXPRESS" && p.availability !== "OUT";

  const actionBtn = canAdd
    ? `<button class="btn primary small" data-add="${p.id}">Agregar</button>`
    : (p.type === "EXPRESS"
        ? `<button class="btn small" disabled>No disponible</button>`
        : `<a class="btn primary small" href="./custom.html?type=${p.type}">Solicitar</a>`);

  return `
    <div class="card">
      <div class="cardImg">
        ${img}
        <div class="badge ${typeBadge}${outBadge}">${badgeText}</div>
      </div>
      <div class="cardBody">
        <div class="kicker">Pastelería</div>
        <h4 class="cardTitle">${p.name}</h4>
        <p class="cardDesc">${desc}${desc.length>=92?'…':''}</p>

        <div class="meta">
          <div class="price">
            ${priceMain}
            <small>${priceSub}</small>
          </div>
          <div>${actionBtn}</div>
        </div>
      </div>
    </div>
  `;
}

export function validatePhone(phone) {
  const clean = phone.replace(/\D/g, '');
  return clean.length >= 8;
}

export function getMinDate(type) {
  const now = new Date();
  let hoursToAdd = 0;
  
  if (type === "PACK") hoursToAdd = 24;
  else if (type === "CUSTOM") hoursToAdd = 72;
  // EXPRESS = 0 hours
  
  now.setHours(now.getHours() + hoursToAdd);
  
  // Format to YYYY-MM-DD
  const yyyy = now.getFullYear();
  const mm = String(now.getMonth() + 1).padStart(2, '0');
  const dd = String(now.getDate()).padStart(2, '0');
  
  return `${yyyy}-${mm}-${dd}`;
}
