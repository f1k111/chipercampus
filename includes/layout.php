<?php
/**
 * CipherCampus - Komponen Layout Reusable
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

function render_head(string $title = '', string $extraCss = ''): void
{
    $fullTitle = ($title ? $title . ' — ' : '') . APP_NAME;
    ?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($fullTitle, ENT_QUOTES) ?></title>
    <meta name="description" content="Sistem informasi mahasiswa dengan enkripsi hybrid AES-256-GCM + RSA-2048">
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/favicon.svg">
    <!-- Google Fonts: Space Grotesk, Plus Jakarta Sans, JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
    <?= $extraCss ?>
</head>
<body>
    <?php
}

function render_admin_layout_start(string $pageTitle = '', string $activeNav = ''): void
{
    init_session();
    $remaining = session_remaining();
    ?>
<div class="admin-layout">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="<?= BASE_URL ?>/admin/dashboard.php" class="sidebar-brand">
                <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="28" height="28" rx="8" fill="#C8F169" fill-opacity="0.15"/>
                    <path d="M14 6C10.686 6 8 8.686 8 12v2H7a1 1 0 00-1 1v7a1 1 0 001 1h14a1 1 0 001-1v-7a1 1 0 00-1-1h-1v-2c0-3.314-2.686-6-6-6zm0 2c2.206 0 4 1.794 4 4v2h-8v-2c0-2.206 1.794-4 4-4zm0 8a1.5 1.5 0 110 3 1.5 1.5 0 010-3z" fill="#C8F169"/>
                </svg>
                <span>CipherCampus</span>
            </a>
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </button>
        </div>
        <nav class="sidebar-nav">
            <a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-item <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="1" y="1" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/><rect x="11" y="1" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/><rect x="1" y="11" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/><rect x="11" y="11" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/></svg>
                <span>Dashboard</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/mahasiswa.php" class="nav-item <?= $activeNav === 'mahasiswa' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="6" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M3 16c0-3.314 2.686-6 6-6s6 2.686 6 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                <span>Mahasiswa</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/nilai.php" class="nav-item <?= $activeNav === 'nilai' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M3 14l4-4 3 3 5-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><rect x="1" y="1" width="16" height="16" rx="2" stroke="currentColor" stroke-width="1.5"/></svg>
                <span>Nilai</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/inspector.php" class="nav-item <?= $activeNav === 'inspector' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="8" cy="8" r="5" stroke="currentColor" stroke-width="1.5"/><path d="M12 12l4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                <span>Inspector</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="<?= BASE_URL ?>/logout.php" class="nav-item nav-logout">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M7 16H3a1 1 0 01-1-1V3a1 1 0 011-1h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M12 13l4-4-4-4M16 9H7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main area -->
    <div class="admin-main">
        <!-- Topbar -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle-mobile" id="sidebarToggleMobile" aria-label="Buka menu">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                </button>
                <h1 class="page-title"><?= htmlspecialchars($pageTitle, ENT_QUOTES) ?></h1>
            </div>
            <div class="topbar-right">
                <div class="session-timer" id="sessionTimer" data-remaining="<?= $remaining ?>">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.2"/><path d="M7 4v3l2 1.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                    <span id="timerDisplay">--:--</span>
                </div>
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle tema">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" class="icon-dark"><path d="M8 1a7 7 0 100 14A7 7 0 008 1zm0 1.5a5.5 5.5 0 010 11V2.5z"/></svg>
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" class="icon-light" style="display:none"><circle cx="8" cy="8" r="3.5"/><path d="M8 1v1.5M8 13.5V15M15 8h-1.5M2.5 8H1M12.95 3.05l-1.06 1.06M4.11 11.89l-1.06 1.06M12.95 12.95l-1.06-1.06M4.11 4.11L3.05 3.05" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                </button>
                <a href="<?= BASE_URL ?>/logout.php" class="btn btn-sm btn-ghost">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M5 12H2a1 1 0 01-1-1V3a1 1 0 011-1h3M9 10l3-3-3-3M12 7H5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Logout
                </a>
            </div>
        </header>

        <!-- Content area -->
        <main class="admin-content">
    <?php
}

function render_admin_layout_end(): void
{
    ?>
        </main>
    </div><!-- .admin-main -->
</div><!-- .admin-layout -->
<div id="toastContainer" class="toast-container" aria-live="polite"></div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
    <?php
}

function render_foot(string $extraJs = ''): void
{
    ?>
<div id="toastContainer" class="toast-container" aria-live="polite"></div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<?= $extraJs ?>
</body>
</html>
    <?php
}
