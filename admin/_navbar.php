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
    }

    .admin-nav {
        background: rgba(0, 79, 57, 0.94); /* Translúcido para efecto Glass */
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        color: var(--admin-bg);
        padding: 8px 20px; /* Un poco más delgado */
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 1000;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        margin: -16px -16px 20px -16px; /* Offset body margin */
        flex-wrap: wrap; /* Ayuda en pantallas medianas */
    }

    .admin-nav-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
    }

    /* --- ESTILO DEL LOGO --- */
    .admin-logo {
        height: 45px; /* Tamaño perfecto para que el menú no sea muy gordo */
        width: auto;
        object-fit: contain;
        transition: transform 0.2s;
    }
    .admin-logo:hover {
        transform: scale(1.05);
    }

    .admin-nav-brand span {
        color: var(--admin-bg);
        font-family: system-ui, sans-serif;
        font-size: 14px;
        opacity: 0.8;
    }

    .admin-nav-links {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .admin-nav-links a {
        color: var(--admin-bg);
        text-decoration: none;
        padding: 0.6rem 0.9rem;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 6px;
        font-family: system-ui, sans-serif;
    }

    .admin-nav-links a:hover {
        background: rgba(255, 250, 202, 0.15);
        transform: translateY(-1px);
    }

    .admin-nav-links a.active {
        background: var(--admin-bg);
        color: var(--admin-primary);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    /* Agrupamos usuario y botones derechos */
    .admin-right-section {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .admin-nav-user {
        font-size: 0.85rem;
        font-family: system-ui, sans-serif;
    }

    .btn-logout {
        background: #b00020;
        color: #fff !important;
        border: none;
        padding: 6px 14px;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        font-weight: bold;
        transition: background 0.2s;
    }
    
    .btn-logout:hover {
        background: #8a0019;
    }

    .mobile-toggle {
        display: none;
        background: none;
        border: none;
        color: #fffaca;
        font-size: 1.8rem;
        cursor: pointer;
        padding: 0 5px;
    }

    /* --- RESPONSIVIDAD PARA CELULARES --- */
    @media (max-width: 992px) {
        .admin-nav {
            padding: 10px 15px;
        }

        .mobile-toggle {
            display: block;
            z-index: 1001; /* Queda por encima del panel deslizable */
        }

        .admin-nav-links {
            position: fixed;
            top: 0;
            right: -100%;
            height: 100vh;
            width: 260px;
            flex-direction: column;
            padding: 80px 20px 20px 20px;
            margin: 0;
            border-top: none;
            align-items: flex-start;
            background: rgba(0, 79, 57, 0.96);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 0;
            box-shadow: -10px 0 30px rgba(0, 0, 0, 0.2);
            transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .admin-nav-links.show {
            right: 0 !important;
            display: flex !important;
        }

        .admin-nav-links a {
            width: 100%;
            padding: 15px 10px;
            justify-content: flex-start;
            font-size: 1.1rem;
            border-bottom: 1px solid rgba(255, 250, 202, 0.08);
            border-radius: 0;
        }

        .admin-nav-links a:last-child {
            border-bottom: none;
        }

        /* Ocultar elementos secundarios en celular para ganar espacio */
        .desktop-only { display: none !important; }
    }
</style>

<nav class="admin-nav">
    <a href="/sweetpath/admin/orders.php" class="admin-nav-brand">
        <img src="/sweetpath/public/img/logo_white.png" alt="ESENCIA" class="admin-logo">
        <span class="desktop-only">| Admin</span>
    </a>

    <div class="admin-right-section">
        <div class="admin-nav-user desktop-only">
            <span>👤 <?= htmlspecialchars($admin_user) ?></span>
        </div>
        <a href="/sweetpath/admin/logout.php" class="btn-logout">Salir</a>
        <button class="mobile-toggle" onclick="document.querySelector('.admin-nav-links').classList.toggle('show')">☰</button>
    </div>

    <div class="admin-nav-links">
        <a href="/sweetpath/admin/orders.php" class="<?= is_active_c('orders.php', $current_page) ?>">📦 Pedidos</a>
        <a href="/sweetpath/admin/payments.php" class="<?= is_active_c('payments.php', $current_page) ?>">💳 Pagos</a>
        <a href="/sweetpath/admin/estadisticas.php" class="<?= is_active_c('estadisticas.php', $current_page) ?>">📊 Estadísticas</a>
        <a href="/sweetpath/admin/products.php" class="<?= is_active_c('products.php', $current_page) ?>">🧁 Productos</a>
        <a href="/sweetpath/admin/promos.php" class="<?= is_active_c('promos.php', $current_page) ?>">📣 Promos</a>
        <a href="/sweetpath/admin/config.php" class="<?= is_active_c('config.php', $current_page) ?>">⚙️ Config</a>
        
        <a href="/sweetpath/public/index.html" target="_blank" style="background: rgba(255,250,202,0.1); justify-content: center; margin-top: 5px;">🌐 Ver Tienda</a>
    </div>
</nav>