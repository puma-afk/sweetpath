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
        background: var(--admin-primary);
        color: var(--admin-bg); /* Use brand cream for text */
        padding: 0.5rem 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 1000;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        margin: -16px -16px 20px -16px; /* Offset body margin */
    }

    .admin-nav-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        color: inherit;
    }

    .admin-nav-brand h1 {
        font-family: 'Playfair Display', serif;
        font-size: 1.4rem;
        margin: 0;
        color: var(--admin-bg);
        font-weight: 700;
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
        border-radius: 12px;
        font-size: 0.95rem;
        font-weight: 600;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 6px;
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

    .admin-nav-user {
        display: flex;
        align-items: center;
        gap: 15px;
        font-size: 0.85rem;
    }

    .btn-logout {
        background: #b00020;
        color: #fff;
        border: none;
        padding: 5px 12px;
        border-radius: 6px;
        cursor: pointer;
        text-decoration: none;
        font-weight: 600;
    }

    .mobile-toggle {
        display: none;
        background: none;
        border: none;
        color: #fffaca;
        font-size: 1.5rem;
        cursor: pointer;
    }

    @media (max-width: 992px) {
        .admin-nav-links {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--admin-primary);
            flex-direction: column;
            padding: 1rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .admin-nav-links.show {
            display: flex;
        }

        .mobile-toggle {
            display: block;
        }
    }
</style>

<nav class="admin-nav">
    <a href="/sweetpath/admin/orders.php" class="admin-nav-brand">
        <h1>ESENCIA Admin</h1>
    </a>

    <button class="mobile-toggle" onclick="document.querySelector('.admin-nav-links').classList.toggle('show')">☰</button>

    <div class="admin-nav-links">
        <a href="/sweetpath/admin/orders.php" class="<?= is_active_c('orders.php', $current_page) ?>">📦 Pedidos</a>
        <a href="/sweetpath/admin/payments.php" class="<?= is_active_c('payments.php', $current_page) ?>">💳 Pagos</a>
        <a href="/sweetpath/admin/estadisticas.php" class="<?= is_active_c('estadisticas.php', $current_page) ?>">📊 Estadísticas</a>
        <a href="/sweetpath/admin/products.php" class="<?= is_active_c('products.php', $current_page) ?>">🧁 Productos</a>
        <a href="/sweetpath/admin/promos.php" class="<?= is_active_c('promos.php', $current_page) ?>">📣 Promos</a>
        <a href="/sweetpath/admin/config.php" class="<?= is_active_c('config.php', $current_page) ?>">⚙️ Config</a>
        <div style="border-left: 1px solid rgba(255,255,255,0.2); height: 20px; margin: 0 10px;" class="desktop-only"></div>
        <a href="/sweetpath/public/index.html" target="_blank">🌐 Ver Tienda</a>
    </div>

    <div class="admin-nav-user">
        <span class="desktop-only">👤 <?= htmlspecialchars($admin_user) ?></span>
        <a href="/sweetpath/admin/logout.php" class="btn-logout">Salir</a>
    </div>
</nav>

<style>
    @media (max-width: 600px) {
        .desktop-only { display: none; }
    }
</style>
