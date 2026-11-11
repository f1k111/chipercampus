<?php
/**
 * CipherCampus - Setup Kunci Pertama Kali
 * Hanya bisa diakses jika app_keys masih kosong.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/crypto.php';
require_once __DIR__ . '/includes/auth.php';

security_headers();
init_session();

// Cek apakah sudah ada kunci
$pdo = db();
$count = (int)$pdo->query("SELECT COUNT(*) FROM app_keys")->fetchColumn();
if ($count > 0) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$error   = '';
$success = false;
$kSymHex = '';
$privPem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Generate K_sym
        $kSym    = CryptoService::generateKSym();
        $kSymHex = CryptoService::kSymToHex($kSym);

        // Generate RSA key pair
        $keys    = CryptoService::generateRsaKeyPair();
        $pubPem  = $keys['public'];
        $privPem = $keys['private'];

        // Buat sym_check
        $symCheck = CryptoService::makeSymCheck($kSym);

        // Buat RSA challenge
        $challenge = CryptoService::makeRsaChallenge($pubPem);

        // Simpan ke DB
        $stmt = $pdo->prepare(
            "INSERT INTO app_keys (rsa_public_key, sym_check, rsa_challenge_ct, rsa_challenge_hash)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([
            $pubPem,
            $symCheck,
            $challenge['rsa_challenge_ct'],
            $challenge['rsa_challenge_hash'],
        ]);

        $success = true;
    } catch (Throwable $e) {
        $error = 'Gagal generate kunci: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES);
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Kunci — <?= APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
    <script>window.BASE_URL = '<?= BASE_URL ?>';</script>
</head>
<body>
<div class="auth-page" style="align-items:flex-start;padding-top:40px">
    <div style="width:100%;max-width:600px;margin:0 auto">
        <div class="auth-logo" style="margin-bottom:0;flex-direction:row;text-align:left;justify-content:flex-start;margin-bottom:24px">
            <a href="<?= BASE_URL ?>/" style="display:flex;align-items:center;gap:10px;color:var(--text);text-decoration:none;font-family:var(--font-head);font-weight:700">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none"><rect width="32" height="32" rx="8" fill="#C8F169" fill-opacity="0.15"/><path d="M16 6C12.134 6 9 9.134 9 13v2.5H8a1.5 1.5 0 00-1.5 1.5v8A1.5 1.5 0 008 26.5h16a1.5 1.5 0 001.5-1.5V17A1.5 1.5 0 0024 15.5h-1V13c0-3.866-3.134-7-7-7zm0 2.5c2.485 0 4.5 2.015 4.5 4.5v2.5h-9V13c0-2.485 2.015-4.5 4.5-4.5zm0 9.5a2 2 0 110 4 2 2 0 010-4z" fill="#C8F169"/></svg>
                <?= APP_NAME ?>
            </a>
        </div>

        <?php if (!$success): ?>
        <div class="card" style="background-image:none">
            <h1 style="font-size:22px;margin-bottom:8px">Setup Kunci Kriptografi</h1>
            <p style="color:var(--text-dim);font-size:14px;margin-bottom:24px">
                Halaman ini hanya bisa diakses sekali. Kunci publik RSA dan verifikasi login akan tersimpan di database.
                <strong>Kunci privat dan K_sym tidak pernah disimpan di server</strong>—simpan baik-baik!
            </p>

            <?php if ($error): ?>
            <div class="auth-error">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M8 5v4M8 11v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                <?= $error ?>
            </div>
            <?php endif; ?>

            <div class="setup-warning" style="margin-bottom:24px">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" style="flex-shrink:0;margin-top:1px"><path d="M9 2L1 16h16L9 2z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M9 7v4M9 13v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                <div>
                    <strong>Perhatian penting:</strong> Setelah kunci di-generate, Anda akan mendapatkan K_sym dan kunci privat RSA.
                    <strong>Kehilangan kunci = seluruh data tidak bisa dibuka kembali.</strong>
                    Simpan di tempat aman (password manager, USB terenkripsi, dll).
                </div>
            </div>

            <form method="POST">
                <button type="submit" class="btn btn-primary btn-lg w-full">
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 2l2.5 5.5H17l-4.5 3.3 1.7 5.7L9 13.4l-5.2 3.1 1.7-5.7L1 7.5h5.5L9 2z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                    Generate Kunci Sekarang
                </button>
            </form>
        </div>

        <?php else: ?>
        <!-- Tampilkan kunci yang baru dibuat -->
        <div class="card" style="background-image:none">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
                <div style="width:40px;height:40px;background:var(--accent-dim);border-radius:50%;display:flex;align-items:center;justify-content:center;border:1px solid rgba(200,241,105,0.3)">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M4 10l4 4 8-8" stroke="#C8F169" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div>
                    <h2 style="font-size:18px">Kunci Berhasil Di-generate!</h2>
                    <p style="font-size:13px;color:var(--text-dim)">Simpan kedua kunci di bawah sebelum melanjutkan.</p>
                </div>
            </div>

            <div class="setup-warning" style="margin-bottom:24px">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" style="flex-shrink:0;margin-top:1px"><path d="M9 2L1 16h16L9 2z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M9 7v4M9 13v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                <div><strong>SIMPAN SEKARANG.</strong> Halaman ini tidak akan bisa diakses lagi. Jika Anda menutup halaman ini sebelum menyimpan kunci, data akan terkunci permanen.</div>
            </div>

            <!-- K_sym -->
            <div class="form-group" style="margin-bottom:20px">
                <label>Kunci Simetris (K_sym) — 64 karakter hex</label>
                <div class="key-display" id="kSymDisplay"><?= htmlspecialchars($kSymHex, ENT_QUOTES) ?></div>
                <div style="display:flex;gap:8px;margin-top:8px">
                    <button class="copy-btn" data-copy="#kSymDisplay">
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><rect x="4" y="4" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.2"/><path d="M4 4V2a1 1 0 00-1-1H2a1 1 0 00-1 1v5a1 1 0 001 1h2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                        Salin K_sym
                    </button>
                </div>
            </div>

            <!-- Kunci Privat -->
            <div class="form-group" style="margin-bottom:24px">
                <label>Kunci Privat RSA — PEM</label>
                <div class="key-display" id="privPemDisplay" style="max-height:200px;overflow-y:auto;font-size:11px"><?= htmlspecialchars($privPem, ENT_QUOTES) ?></div>
                <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
                    <button class="copy-btn" data-copy="#privPemDisplay">
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><rect x="4" y="4" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.2"/><path d="M4 4V2a1 1 0 00-1-1H2a1 1 0 00-1 1v5a1 1 0 001 1h2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                        Salin PEM
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="downloadPrivKey()">
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M7 2v7M4 6l3 3 3-3M2 11h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Unduh ciphercampus-private.pem
                    </button>
                </div>
            </div>

            <!-- Checkbox konfirmasi -->
            <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:24px">
                <label class="checkbox-wrap">
                    <input type="checkbox" id="chk1" onchange="checkConfirm()">
                    <span>Saya sudah menyimpan <strong>Kunci Simetris (K_sym)</strong> di tempat yang aman.</span>
                </label>
                <label class="checkbox-wrap">
                    <input type="checkbox" id="chk2" onchange="checkConfirm()">
                    <span>Saya sudah menyimpan/mengunduh <strong>Kunci Privat RSA</strong> (file .pem) di tempat yang aman.</span>
                </label>
                <label class="checkbox-wrap">
                    <input type="checkbox" id="chk3" onchange="checkConfirm()">
                    <span>Saya memahami bahwa <strong>kehilangan kunci = data tidak bisa dibuka kembali.</strong></span>
                </label>
            </div>

            <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary btn-lg w-full" id="continueBtn" style="pointer-events:none;opacity:0.4" aria-disabled="true">
                Lanjut ke Login →
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="toastContainer" class="toast-container" aria-live="polite"></div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
function checkConfirm() {
    const all = document.getElementById('chk1').checked &&
                document.getElementById('chk2').checked &&
                document.getElementById('chk3').checked;
    const btn = document.getElementById('continueBtn');
    if (btn) {
        btn.style.pointerEvents = all ? 'auto' : 'none';
        btn.style.opacity       = all ? '1' : '0.4';
        btn.setAttribute('aria-disabled', all ? 'false' : 'true');
    }
}

function downloadPrivKey() {
    const pem = document.getElementById('privPemDisplay')?.textContent || '';
    const blob = new Blob([pem], { type: 'application/x-pem-file' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = 'ciphercampus-private.pem';
    a.click();
    URL.revokeObjectURL(url);
    window.CC.showToast('File kunci privat diunduh!', 'success');
}
</script>
</body>
</html>
