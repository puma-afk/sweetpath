
export function card(p) {
  const img = p.image_url ? `<img src="${p.image_url}" alt="">` : "";
  const priceMain = p.price_bs ? `Bs ${p.price_bs}` : "Precio a confirmar";
  const priceSub = p.price_bs ? "precio referencial" : "se cotiza por WhatsApp";
  const desc = (p.description || "").slice(0, 92);

  const typeBadge =
    p.type === "EXPRESS" ? "express" :
      p.type === "CUSTOM" ? "custom" : "pack";

  const outBadge = p.availability === "OUT" ? " out" : "";
  let typeDisplay = p.type;
  if (p.type === "EXPRESS") typeDisplay = "RÁPIDO";
  else if (p.type === "CUSTOM") typeDisplay = "PERSONALIZADO";
  else if (p.type === "PACK") typeDisplay = "PACK";

  const availDisplay = p.availability === "OUT" ? "AGOTADO" : p.availability;
  const badgeText = `${typeDisplay} • ${availDisplay}`;

  // Se permite agregar EXPRESS y PACK directamente al carrito (Carrito Mixto)
  const canAdd = (p.type === "EXPRESS" || p.type === "PACK") && p.availability !== "OUT";

  const actionBtn = canAdd
    ? `<button class="btn primary small" style="min-height: 44px;" data-add="${p.id}">Agregar</button>`
    : ((p.type === "EXPRESS" || p.type === "PACK")
      ? `<button class="btn small" style="min-height: 44px;" disabled>Agotado</button>`
      : `<a class="btn primary small" style="min-height: 44px; display:inline-flex; align-items:center;" href="./custom.html?type=${p.type}">Solicitar</a>`);

  return `
    <div class="card">
      <div class="cardImg">
        ${img}
        <div class="badge ${typeBadge}${outBadge}">${badgeText}</div>
      </div>
      <div class="cardBody">
        <div class="kicker">Pastelería</div>
        <h4 class="cardTitle">${p.name}</h4>
        <p class="cardDesc">${desc}${desc.length >= 92 ? '…' : ''}</p>

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

export function tarjeta(p) {
  const img = p.image_url ? `<img src="${p.image_url}" alt="">` : "";
  const priceMain = p.price_bs ? `Bs ${p.price_bs}` : "Cotizar";

  // La API ya reconcilia stock_internal con availability
  const isOut = p.availability === "OUT";
  const isLow = p.availability === "LOW";

  let badgeText = "DISPONIBLE";
  let bgBadge = "var(--primario)"; // Verde marca

  if (isOut) {
    badgeText = "AGOTADO";
    bgBadge = "#e74c3c"; // Rojo
  } else if (isLow) {
    badgeText = "¡ÚLTIMOS!";
    bgBadge = "#f39c12"; // Naranja
  }

  const canAdd = (p.type === "EXPRESS" || p.type === "PACK") && !isOut;

  const actionBtn = canAdd
    ? `<button class="boton primario" style="width:100%; border-radius:8px; padding: 6px 0; font-size: 13px;" data-add="${p.id}"><i class="fas fa-cart-plus"></i></button>`
    : (!isOut
      ? `<a class="boton primario" style="width:100%; border-radius:8px; padding: 6px 0; font-size: 13px; display:inline-flex; align-items:center; justify-content:center;" href="./custom.html?type=${p.type}"><i class="fas fa-paper-plane"></i></a>`
      : `<button class="boton" style="width:100%; border-radius:8px; padding: 6px 0; font-size: 13px; opacity:0.4;" disabled><i class="fas fa-ban"></i></button>`);

  return `
    <div class="tarjeta" data-producto-id="${p.id}" style="cursor:pointer;">
      <div class="img-tarjeta">
        ${p.image_url ? `<img src="${p.image_url}" alt="${p.name}">` : `<div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:var(--primario); opacity:0.1; font-size:1.5rem;"><i class="fas fa-cake-candles"></i></div>`}
        <div class="etiqueta" style="background:${bgBadge}; font-size: 8px; padding: 2px 6px; top: 6px; right: 6px;">${badgeText}</div>
      </div>
      <div class="cuerpo-tarjeta" style="padding: 8px; flex:1; display:flex; flex-direction:column;">
        <h4 class="titulo-tarjeta" style="font-size: 13px; margin: 0 0 4px 0; line-height: 1.2; height: 32px; overflow:hidden;">${p.name}</h4>
        
        <div class="meta-tarjeta" style="margin-top: auto; display:block;">
          <div class="precio" style="margin-bottom: 6px;">
            <span style="font-weight:900; font-size:0.95rem; color:var(--primario);">${priceMain}</span>
          </div>
          ${actionBtn}
        </div>
      </div>
    </div>
  `;
}
