<?php
/**
 * CipherCampus - Halaman Login (2 Kunci)
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

security_headers();
init_session();

// Redirect jika sudah login
if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

// Cek apakah kunci sudah di-setup
$pdo = db();
$hasKeys = (int)$pdo->query("SELECT COUNT(*) FROM app_keys")->fetchColumn() > 0;
if (!$hasKeys) {
    header('Location: ' . BASE_URL . '/setup.php');
    exit;
}

$error     = '';
$rateLimit = false;
$ip        = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// Cek apakah sudah kena rate limit
if (!check_rate_limit($ip)) {
    $rateLimit = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$rateLimit) {
    $kSymHex = trim($_POST['k_sym']  ?? '');
    $privPem = trim($_POST['priv_pem'] ?? '');

    // Jika upload file .pem
    if (isset($_FILES['priv_file']) && $_FILES['priv_file']['error'] === UPLOAD_ERR_OK) {
        $privPem = file_get_contents($_FILES['priv_file']['tmp_name']);
    }

    $result = attempt_login($kSymHex, $privPem);

    if ($result['ok']) {
        $next = $_GET['next'] ?? '';
        // Validasi next URL harus dalam aplikasi kita
        if (!str_starts_with($next, BASE_URL . '/admin/')) {
            $next = BASE_URL . '/admin/dashboard.php';
        }
        header('Location: ' . $next);
        exit;
    } else {
        if ($result['error'] === 'rate_limit') {
            $rateLimit = true;
        } else {
            $error = $result['msg'];
        }
    }

    // Refresh rate limit check
    if (!check_rate_limit($ip)) $rateLimit = true;
}

$failedCount = get_failed_count($ip);
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
    <script>window.BASE_URL = '<?= BASE_URL ?>';</script>
</head>
<body>
<div class="auth-page">
    <div style="width:100%;max-width:480px">

        <!-- Back link -->
        <a href="<?= BASE_URL ?>/" style="display:inline-flex;align-items:center;gap:6px;color:var(--text-dim);font-size:13px;margin-bottom:20px;text-decoration:none">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M9 11L5 7l4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Kembali ke beranda
        </a>

        <div class="auth-card">
            <div class="auth-logo">
                <div class="auth-logo-icon">
                    <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
                        <path d="M14 4C10.134 4 7 7.134 7 11v2.5H6a1.5 1.5 0 00-1.5 1.5v7A1.5 1.5 0 006 23.5h16a1.5 1.5 0 001.5-1.5V15a1.5 1.5 0 00-1.5-1.5h-1V11c0-3.866-3.134-7-7-7zm0 2.5a4.5 4.5 0 014.5 4.5v2.5h-9V11A4.5 4.5 0 0114 6.5zm0 10a2 2 0 110 4 2 2 0 010-4z" fill="#C8F169"/>
                    </svg>
                </div>
                <div class="auth-logo-title"><?= APP_NAME ?></div>
                <div class="auth-logo-sub">Login dengan dua kunci kriptografi</div>
            </div>

            <?php if (isset($_GET['reason']) && $_GET['reason'] === 'timeout'): ?>
            <div class="auth-error" style="margin-bottom:16px">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M8 5v4M8 11v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                Sesi berakhir karena tidak aktif selama 15 menit.
            </div>
            <?php endif; ?>

            <?php if ($rateLimit): ?>
            <div class="auth-error">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M6 6l4 4M10 6l-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                <div>
                    <strong>Terlalu banyak percobaan gagal.</strong><br>
                    Tunggu 10 menit sebelum mencoba lagi.
                </div>
            </div>

            <?php else: ?>

            <?php if ($error): ?>
            <div class="auth-error" id="errorBox">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M6 6l4 4M10 6l-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                <?= htmlspecialchars($error, ENT_QUOTES) ?>
                <?php if ($failedCount > 0): ?>
                <div style="font-size:12px;margin-top:4px;opacity:0.8"><?= $failedCount ?>/5 percobaan</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="auth-form" id="loginForm" novalidate>
                <?= csrf_field() ?>

                <!-- Kunci Simetris -->
                <div class="form-group">
                    <label for="k_sym">Kunci Simetris (K_sym)</label>
                    <div class="input-group">
                        <input
                            type="text"
                            id="k_sym"
                            name="k_sym"
                            class="input-mono"
                            placeholder="64 karakter hex (a-f 0-9)..."
                            autocomplete="off"
                            spellcheck="false"
                            maxlength="64"
                            required
                            aria-describedby="kSymHint"
                        >
                        <button type="button" class="input-action" id="toggleKSym" aria-label="Tampilkan/sembunyikan" title="Tampilkan/sembunyikan">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" id="eyeIconSym"><path d="M1 8s3-5 7-5 7 5 7 5-3 5-7 5-7-5-7-5z" stroke="currentColor" stroke-width="1.5"/><circle cx="8" cy="8" r="2" stroke="currentColor" stroke-width="1.5"/></svg>
                        </button>
                    </div>
                    <span class="hint" id="kSymHint">64 karakter hex (256-bit). Contoh: a3f1...</span>
                    <span class="hint error" id="kSymError" style="display:none"></span>
                </div>

                <!-- Kunci Privat RSA -->
                <div class="form-group">
                    <label>Kunci Privat RSA</label>
                    <textarea
                        id="priv_pem"
                        name="priv_pem"
                        class="input-mono"
                        placeholder="-----BEGIN RSA PRIVATE KEY-----&#10;...atau paste konten .pem di sini..."
                        style="min-height:120px;font-size:12px"
                        spellcheck="false"
                        aria-describedby="privPemHint"
                    ></textarea>
                    <span class="hint" id="privPemHint">Atau upload file .pem:</span>
                    <!-- Drop zone upload file -->
                    <div class="file-drop" data-target="priv_pem" style="margin-top:8px">
                        <input type="file" name="priv_file" accept=".pem" style="display:none">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" style="color:var(--text-muted);margin:0 auto 6px"><path d="M12 16V8M9 11l3-3 3 3M4 16a4 4 0 010-8h.5A5 5 0 0119 10.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <div class="drop-label" style="font-size:13px;color:var(--text-dim)">Klik atau seret file <code>.pem</code></div>
                    </div>
                    <span class="hint error" id="privPemError" style="display:none"></span>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-full" id="submitBtn">
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 3C6.239 3 4 5.239 4 8v1.5H3a1 1 0 00-1 1v5a1 1 0 001 1h12a1 1 0 001-1v-5a1 1 0 00-1-1h-1V8c0-2.761-2.239-5-5-5zm0 2a3 3 0 013 3v1.5H6V8a3 3 0 013-3zm0 6a1.5 1.5 0 110 3 1.5 1.5 0 010-3z" fill="currentColor"/></svg>
                    Masuk ke Admin
                </button>
            </form>

            <?php endif; ?>
        </div><!-- .auth-card -->
    </div>
</div>

<div id="toastContainer" class="toast-container" aria-live="polite"></div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
// Validasi format real-time
document.addEventListener('DOMContentLoaded', () => {
    const kSymInput  = document.getElementById('k_sym');
    const privPemTA  = document.getElementById('priv_pem');
    const kSymError  = document.getElementById('kSymError');
    const privPemErr = document.getElementById('privPemError');
    const form       = document.getElementById('loginForm');
    const errorBox   = document.getElementById('errorBox');

    // Toggle K_sym visibility
    document.getElementById('toggleKSym')?.addEventListener('click', () => {
        const inp = document.getElementById('k_sym');
        inp.type = inp.type === 'password' ? 'text' : 'password';
    });

    kSymInput?.addEventListener('input', () => {
        const val = kSymInput.value.replace(/\s/g, '');
        kSymInput.value = val;
        if (val.length === 0) {
            kSymInput.classList.remove('input-error');
            kSymError.style.display = 'none';
        } else if (val.length !== 64 || !/^[0-9a-fA-F]+$/.test(val)) {
            kSymInput.classList.add('input-error');
            kSymError.textContent = `Harus 64 karakter hex (sekarang: ${val.length})`;
            kSymError.style.display = 'block';
        } else {
            kSymInput.classList.remove('input-error');
            kSymError.style.display = 'none';
        }
    });

    privPemTA?.addEventListener('input', () => {
        const val = privPemTA.value.trim();
        if (val.length > 0 && !val.includes('BEGIN')) {
            privPemTA.classList.add('input-error');
            privPemErr.textContent = 'Harus berformat PEM (dimulai dengan -----BEGIN...)';
            privPemErr.style.display = 'block';
        } else {
            privPemTA.classList.remove('input-error');
            privPemErr.style.display = 'none';
        }
    });

    // Shake animation saat ada error
    <?php if ($error): ?>
    document.getElementById('loginForm')?.classList.add('shake');
    <?php endif; ?>
});
</script>
</body>
</html>
