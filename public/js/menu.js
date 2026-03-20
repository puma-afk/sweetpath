/**
 * menu.js — Inicialización sincrónica del menú de navegación
 * Funciona con tienda.css (.boton-menu / .enlaces-nav.activo)
 * y con app.css (.menu-toggle / .navlinks.active)
 * No requiere imports ni esperar APIs async.
 */
(function() {
  function bindMenu() {
    const toggle = document.querySelector('.boton-menu, .menu-toggle');
    const navlinks = document.querySelector('.enlaces-nav, .navlinks');
    if (!toggle || !navlinks) return;
    if (toggle.dataset.navBound) return;
    toggle.dataset.navBound = 'true';

    const capa = document.querySelector('.capa-menu, .menu-overlay');
    const icon = toggle.querySelector('i') || document.getElementById('menuIcon');

    function setIcon(open) {
      if (icon) icon.className = open ? 'fas fa-times' : 'fas fa-bars';
    }

    function closeMenu() {
      navlinks.classList.remove('activo', 'active');
      navlinks.style.zIndex = '';
      const header = toggle.closest('.navegacion, .nav');
      if (header) header.style.zIndex = '';
      if (capa) capa.classList.remove('activo', 'active');
      setIcon(false);
    }

    toggle.addEventListener('click', function(e) {
      e.stopPropagation();
      const isOpen = navlinks.classList.contains('activo') || navlinks.classList.contains('active');
      if (!isOpen) {
        navlinks.classList.add('activo', 'active');
        navlinks.style.zIndex = '20000';
        
        // Elevar el header padre para asegurar el stacking context
        const header = toggle.closest('.navegacion, .nav');
        if (header) header.style.zIndex = '20000';

        if (capa) {
          capa.classList.add('activo', 'active');
          capa.style.zIndex = '19999';
        }
        setIcon(true);
      } else {
        closeMenu();
      }
    });

    if (capa) {
      capa.addEventListener('click', closeMenu);
    }

    document.addEventListener('click', function(e) {
      if (
        (navlinks.classList.contains('activo') || navlinks.classList.contains('active')) &&
        !navlinks.contains(e.target) &&
        !toggle.contains(e.target)
      ) {
        closeMenu();
      }
    });

    window.addEventListener('resize', function() {
      if (window.innerWidth > 800) closeMenu();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindMenu);
  } else {
    bindMenu();
  }
})();

// ── PWA Install Logic ──
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPrompt = e;

  const navs = document.querySelectorAll('.enlaces-nav, .navlinks');
  navs.forEach(nav => {
    if (!nav.querySelector('.pwa-install-btn')) {
      const btn = document.createElement('button');
      btn.className = 'pwa-install-btn';
      btn.innerHTML = '<i class="fas fa-download"></i> Instalar App';
      btn.style.cssText = `
        display: inline-flex; align-items: center; gap: 8px; 
        background: var(--primario, #004f39); color: var(--fondo, #fffaca); 
        border: none; border-radius: 20px; padding: 8px 16px; 
        font-weight: bold; cursor: pointer; font-size: 0.95rem;
        margin: 10px; box-shadow: 0 4px 10px rgba(0,79,57,0.2);
        transition: transform 0.2s;
      `;
      btn.onmouseover = () => btn.style.transform = 'scale(1.05)';
      btn.onmouseout = () => btn.style.transform = 'scale(1)';

      btn.addEventListener('click', async () => {
        btn.style.display = 'none';
        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        console.log(`PWA Install outcome: ${outcome}`);
        deferredPrompt = null;
      });

      nav.appendChild(btn);
    }
  });
});
