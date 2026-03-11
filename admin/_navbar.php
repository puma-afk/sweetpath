<?php
// admin/_navbar.php

if (!isset($pdo)) {
    require_once __DIR__ . '/../db.php';
}

$current_page = basename($_SERVER['PHP_SELF']);
$admin_user = $_SESSION['admin_user']['username'] ?? 'admin';

function is_active_c($page, $current) {
    return $page === $current ? 'active' : '';
}
?>
<style>
    :root {
        --admin-primary: #004f39; /* Verde Oscuro Esencia */
        --admin-bg: #fffaca;      /* Crema Suave Esencia */
        --admin-text: #151613;
        --admin-accent: #ffd32a;
        --nav-height: 70px;
    }

    .admin-nav {
        background: rgba(0, 79, 57, 0.95);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        color: var(--admin-bg);
        height: var(--nav-height);
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 2000;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        padding: 0 20px;
        margin: 0 0 0 0;
        width: 100%;
        box-sizing: border-box;
    }

    .admin-nav-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
    }

    .admin-logo {
        height: 40px;
        width: auto;
        transition: transform 0.3s ease;
    }
    .admin-logo:hover { transform: scale(1.05); }

    .brand-text {
        display: flex;
        flex-direction: column;
        line-height: 1;
    }
    .brand-text b { font-size: 18px; letter-spacing: 1px; color: #fffaca; }
    .brand-text small { font-size: 10px; opacity: 0.7; color: #fffaca; }

    .admin-nav-links {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .admin-nav-links a {
        color: var(--admin-bg);
        text-decoration: none;
        padding: 8px 14px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .admin-nav-links a:hover {
        background: rgba(255, 250, 202, 0.1);
    }

    .admin-nav-links a.active {
        background: var(--admin-bg);
        color: var(--admin-primary);
    }

    .admin-right-section {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        opacity: 0.9;
    }

    .btn-logout {
        background: #ef4444;
        color: #fff !important;
        padding: 8px 16px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        transition: background 0.2s;
    }
    .btn-logout:hover { background: #dc2626; }

    .mobile-toggle {
        display: none;
        background: none;
        border: none;
        color: var(--admin-bg);
        font-size: 24px;
        cursor: pointer;
        padding: 5px;
    }

    .nav-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        z-index: 1900;
    }
    .nav-overlay.active { opacity: 1; visibility: visible; }

    @media (max-width: 1024px) {
        .admin-nav-links {
            position: fixed;
            top: 0;
            right: -320px;
            width: 100%;
            max-width: 300px;
            height: 100vh;
            background: rgba(0, 79, 57, 0.90);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 80px 20px 40px;
            gap: 15px;
            overflow-y: auto;
            transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.4s;
            z-index: 2100;
            box-shadow: -15px 0 40px rgba(0,0,0,0.4);
            visibility: hidden;
            border-left: 1px solid rgba(255, 250, 202, 0.1);
        }
        .admin-nav-links.active { right: 0; visibility: visible; }
        
        .admin-nav-links a {
            width: 90%;
            font-size: 1.1rem;
            padding: 15px;
            justify-content: center;
            border-bottom: 1px solid rgba(255, 250, 202, 0.1);
            border-radius: 0;
        }
        .admin-nav-links a:last-child { border-bottom: none; }

        .admin-nav-links a.active {
            background: transparent;
            color: var(--admin-accent);
            font-weight: 800;
        }

        .mobile-toggle { display: block; }
        .desktop-only { display: none !important; }
    }
</style>

<div class="nav-overlay" id="navOverlay"></div>

<nav class="admin-nav">
    <a href="/sweetpath/admin/orders.php" class="admin-nav-brand">
        <img src="/sweetpath/public/img/logo_white.png" alt="ESENCIA" class="admin-logo">
        <div class="brand-text">
            <b>ESENCIA</b>
            <small>Panel Administrativo</small>
        </div>
    </a>

    <div class="admin-nav-links" id="adminNavLinks">
        <a href="/sweetpath/admin/orders.php" class="<?= is_active_c('orders.php', $current_page) ?>">
            <i class="fas fa-box"></i> Pedidos
        </a>
        <a href="/sweetpath/admin/payments.php" class="<?= is_active_c('payments.php', $current_page) ?>">
            <i class="fas fa-credit-card"></i> Pagos
        </a>
        <a href="/sweetpath/admin/estadisticas.php" class="<?= is_active_c('estadisticas.php', $current_page) ?>">
            <i class="fas fa-chart-line"></i> Estadísticas
        </a>
        <a href="/sweetpath/admin/products.php" class="<?= is_active_c('products.php', $current_page) ?>">
            <i class="fas fa-birthday-cake"></i> Productos
        </a>
        <a href="/sweetpath/admin/promos.php" class="<?= is_active_c('promos.php', $current_page) ?>">
            <i class="fas fa-bullhorn"></i> Promos
        </a>
        <a href="/sweetpath/admin/config.php" class="<?= is_active_c('config.php', $current_page) ?>">
            <i class="fas fa-cog"></i> Config
        </a>
        <a href="/sweetpath/public/index.html" target="_blank" style="margin-top: 10px; background: rgba(255,211,42,0.2); color: #ffd32a;">
            <i class="fas fa-external-link-alt"></i> Ver Tienda
        </a>
    </div>

    <div class="admin-right-section">
        <div class="user-info desktop-only">
            <i class="fas fa-user-circle"></i> <span><?= htmlspecialchars($admin_user) ?></span>
        </div>
        <a href="/sweetpath/admin/logout.php" class="btn-logout desktop-only">Salir</a>
        <button class="mobile-toggle" id="adminNavToggle">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</nav>

<script>
(function() {
    const toggle = document.getElementById('adminNavToggle');
    const nav = document.getElementById('adminNavLinks');
    const overlay = document.getElementById('navOverlay');
    const icon = toggle.querySelector('i');

    function toggleMenu() {
        const isOpen = nav.classList.toggle('active');
        overlay.classList.toggle('active');
        icon.className = isOpen ? 'fas fa-times' : 'fas fa-bars';
        document.body.style.overflow = isOpen ? 'hidden' : '';
    }

    toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleMenu();
    });

    overlay.addEventListener('click', toggleMenu);

    window.addEventListener('resize', () => {
        if (window.innerWidth > 1024 && nav.classList.contains('active')) {
            toggleMenu();
        }
    });
})();
</script>