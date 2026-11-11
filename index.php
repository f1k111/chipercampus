<?php
/**
 * CipherCampus - Landing Page
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

security_headers();

// Cek apakah kunci sudah di-setup
$hasKeys = false;
try {
    $stmt = db()->query("SELECT COUNT(*) FROM app_keys");
    $hasKeys = (int)$stmt->fetchColumn() > 0;
} catch (Throwable) {}

$ctaUrl   = $hasKeys ? BASE_URL . '/login.php' : BASE_URL . '/setup.php';
$ctaLabel = $hasKeys ? 'Masuk ke Admin' : 'Mulai Setup Kunci';
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> — Sistem Informasi Mahasiswa Terenkripsi</title>
    <meta name="description" content="Sistem informasi mahasiswa dengan enkripsi hybrid AES-256-GCM + RSA-2048. Proyek UTS <?= htmlspecialchars(APP_COURSE, ENT_QUOTES) ?>.">
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
    <script>window.BASE_URL = '<?= BASE_URL ?>';</script>
</head>
<body>

<!-- Navbar -->
<nav class="landing-nav">
    <a href="#" class="nav-brand">
        <svg width="26" height="26" viewBox="0 0 32 32" fill="none">
            <rect width="32" height="32" rx="8" fill="#C8F169" fill-opacity="0.15"/>
            <path d="M16 6C12.134 6 9 9.134 9 13v2.5H8a1.5 1.5 0 00-1.5 1.5v8A1.5 1.5 0 008 26.5h16a1.5 1.5 0 001.5-1.5V17A1.5 1.5 0 0024 15.5h-1V13c0-3.866-3.134-7-7-7zm0 2.5c2.485 0 4.5 2.015 4.5 4.5v2.5h-9V13c0-2.485 2.015-4.5 4.5-4.5zm0 9.5a2 2 0 110 4 2 2 0 010-4z" fill="#C8F169"/>
        </svg>
        <?= APP_NAME ?>
    </a>
    <div style="display:flex;align-items:center;gap:12px">
        <button class="theme-toggle" id="themeToggleLanding" aria-label="Toggle tema">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" class="icon-dark"><path d="M8 1a7 7 0 100 14A7 7 0 008 1zm0 1.5a5.5 5.5 0 010 11V2.5z"/></svg>
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" class="icon-light" style="display:none"><circle cx="8" cy="8" r="3.5"/><path d="M8 1v1.5M8 13.5V15M15 8h-1.5M2.5 8H1M12.95 3.05l-1.06 1.06M4.11 11.89l-1.06 1.06M12.95 12.95l-1.06-1.06M4.11 4.11L3.05 3.05" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        </button>
        <a href="<?= htmlspecialchars($ctaUrl, ENT_QUOTES) ?>" class="btn btn-primary btn-sm"><?= htmlspecialchars($ctaLabel, ENT_QUOTES) ?></a>
    </div>
</nav>

<!-- Hero -->
<section class="landing-hero">
    <div class="hero-glow"></div>
    <div class="hero-content">
        <div class="hero-eyebrow">
            <svg width="10" height="10" viewBox="0 0 10 10" fill="currentColor"><circle cx="5" cy="5" r="5"/></svg>
            Proyek UTS — <?= htmlspecialchars(APP_COURSE, ENT_QUOTES) ?>
        </div>
        <h1 class="hero-title">
            Data Mahasiswa,<br>
            <span class="hero-cipher-text" id="heroCipherText">DATA TERENKRIPSI</span>
        </h1>
        <p class="hero-subtitle">
            Sistem informasi akademik yang melindungi setiap field data dengan enkripsi hybrid
            <strong>AES-256-GCM + RSA-2048</strong>. Login menggunakan dua kunci kriptografi, bukan username dan password.
        </p>
        <div class="hero-actions">
            <a href="<?= htmlspecialchars($ctaUrl, ENT_QUOTES) ?>" class="btn btn-primary btn-xl">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 3C6.239 3 4 5.239 4 8v1.5H3a1 1 0 00-1 1v5a1 1 0 001 1h12a1 1 0 001-1v-5a1 1 0 00-1-1h-1V8c0-2.761-2.239-5-5-5zm0 2a3 3 0 013 3v1.5H6V8a3 3 0 013-3zm0 6a1.5 1.5 0 110 3 1.5 1.5 0 010-3z" fill="currentColor"/></svg>
                <?= htmlspecialchars($ctaLabel, ENT_QUOTES) ?>
            </a>
            <a href="#cara-kerja" class="btn btn-ghost btn-xl">Lihat Cara Kerja</a>
        </div>
    </div>
</section>

<!-- Cara Kerja -->
<section class="section" id="cara-kerja">
    <p class="section-label">Alur Sistem</p>
    <h2 class="section-title fade-in">Cara Kerja CipherCampus</h2>
    <p class="section-desc fade-in">Empat langkah yang memastikan data mahasiswa tidak pernah tersimpan dalam bentuk terbaca di database.</p>

    <div class="steps-grid">
        <div class="step-card" style="transition-delay:0ms">
            <div class="step-num">01</div>
            <div class="step-icon" style="background:var(--accent-dim)">
                <svg width="22" height="22" viewBox="0 0 22 22" fill="none"><path d="M11 2l3 7h7l-5.5 4 2 7L11 16l-6.5 4 2-7L1 9h7l3-7z" stroke="#C8F169" stroke-width="1.5" stroke-linejoin="round"/></svg>
            </div>
            <h3 class="step-title">Generate Kunci</h3>
            <p class="step-desc">Admin menjalankan <code>setup.php</code> sekali untuk menghasilkan K_sym (256-bit) dan pasangan kunci RSA-2048. Kunci privat <strong>tidak pernah disimpan di server</strong>.</p>
        </div>
        <div class="step-card" style="transition-delay:80ms">
            <div class="step-num">02</div>
            <div class="step-icon" style="background:var(--accent2-dim)">
                <svg width="22" height="22" viewBox="0 0 22 22" fill="none"><rect x="3" y="10" width="16" height="10" rx="2" stroke="#5EEAD4" stroke-width="1.5"/><path d="M7 10V7a4 4 0 018 0v3" stroke="#5EEAD4" stroke-width="1.5" stroke-linecap="round"/></svg>
            </div>
            <h3 class="step-title">Enkripsi Data</h3>
            <p class="step-desc">Setiap baris data diberi DEK acak. Tiap field dienkripsi AES-256-GCM. DEK dibungkus RSA-OAEP lalu AES-GCM lagi—wajib dua kunci untuk membuka.</p>
        </div>
        <div class="step-card" style="transition-delay:160ms">
            <div class="step-num">03</div>
            <div class="step-icon" style="background:var(--cipher-dim)">
                <svg width="22" height="22" viewBox="0 0 22 22" fill="none"><rect x="2" y="4" width="18" height="14" rx="2" stroke="#FFB454" stroke-width="1.5"/><path d="M2 9h18M6 4v5M11 4v5M16 4v5" stroke="#FFB454" stroke-width="1.5" stroke-linecap="round"/></svg>
            </div>
            <h3 class="step-title">Simpan Ciphertext</h3>
            <p class="step-desc">Database hanya berisi ciphertext. Siapa pun yang mengakses phpMyAdmin atau file SQL tidak akan bisa membaca data mahasiswa tanpa kedua kunci.</p>
        </div>
        <div class="step-card" style="transition-delay:240ms">
            <div class="step-num">04</div>
            <div class="step-icon" style="background:var(--danger-dim)">
                <svg width="22" height="22" viewBox="0 0 22 22" fill="none"><circle cx="11" cy="11" r="8" stroke="#FF6B5A" stroke-width="1.5"/><path d="M8 11l2 2 4-4" stroke="#FF6B5A" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <h3 class="step-title">Buka dengan 2 Kunci</h3>
            <p class="step-desc">Login meminta K_sym (64 hex) <em>dan</em> kunci privat RSA. Keduanya wajib benar. Pesan error tidak membocorkan kunci mana yang salah.</p>
        </div>
    </div>
</section>

<!-- Diagram Kripto -->
<section class="section" style="padding-top:0">
    <div class="card fade-in">
        <p class="section-label">Diagram Enkripsi</p>
        <h3 style="font-size:18px;font-family:var(--font-head);margin-bottom:20px">Alur Envelope Encryption</h3>
        <div class="crypto-diagram">
            <div class="crypto-box">
                <div class="label">Plaintext</div>
                <div class="value">nama, NIM, …</div>
            </div>
            <div class="crypto-arrow">→</div>
            <div class="crypto-box highlight">
                <div class="label">AES-256-GCM</div>
                <div class="value">DEK + IV acak</div>
            </div>
            <div class="crypto-arrow">→</div>
            <div class="crypto-box">
                <div class="label">Ciphertext Field</div>
                <div class="value">iv:tag:ct</div>
            </div>
            <div style="width:100%;height:0;flex-basis:100%"></div>
            <div class="crypto-box">
                <div class="label">DEK (32 byte)</div>
                <div class="value">random_bytes(32)</div>
            </div>
            <div class="crypto-arrow">→</div>
            <div class="crypto-box highlight" style="border-color:var(--accent2);background:var(--accent2-dim)">
                <div class="label">RSA-OAEP</div>
                <div class="value">Kunci Publik</div>
            </div>
            <div class="crypto-arrow">→</div>
            <div class="crypto-box highlight" style="border-color:var(--cipher);background:var(--cipher-dim)">
                <div class="label">AES-256-GCM</div>
                <div class="value">K_sym</div>
            </div>
            <div class="crypto-arrow">→</div>
            <div class="crypto-box">
                <div class="label">dek_envelope</div>
                <div class="value">tersimpan di DB</div>
            </div>
        </div>
        <p style="font-size:12px;color:var(--text-muted);margin-top:12px;text-align:center">
            Dekripsi = kebalikan: buka AES K_sym → buka RSA priv → dapat DEK → dekripsi tiap field
        </p>
    </div>
</section>

<!-- Fitur -->
<section class="section" id="fitur" style="padding-top:0">
    <p class="section-label">Fitur</p>
    <h2 class="section-title fade-in">Apa yang Ada di Dalam</h2>
    <div class="features-grid">
        <?php
        $features = [
            ['icon' => '🔐', 'color' => '#C8F169', 'title' => 'Login 2 Kunci', 'desc' => 'Autentikasi menggunakan K_sym hex 64 karakter + kunci privat RSA PEM, bukan username/password.'],
            ['icon' => '🗄️', 'color' => '#5EEAD4', 'title' => 'CRUD Terenkripsi', 'desc' => 'Tambah, ubah, dan hapus data mahasiswa & nilai. Semua tersimpan sebagai ciphertext.'],
            ['icon' => '👁️', 'color' => '#FFB454', 'title' => 'Toggle Ciphertext/Plaintext', 'desc' => 'Toggle besar di tabel untuk melihat ciphertext asli atau plaintext yang didekripsi on-demand.'],
            ['icon' => '🔬', 'color' => '#FF6B5A', 'title' => 'Crypto Inspector', 'desc' => 'Visualisasi lengkap tiap komponen enkripsi: IV, tag, ciphertext, envelope—untuk presentasi dosen.'],
            ['icon' => '🛡️', 'color' => '#C8F169', 'title' => 'GCM Tag Integrity', 'desc' => 'Data yang dimodifikasi manual di DB akan gagal didekripsi dengan pesan jelas (bukan silent error).'],
            ['icon' => '⚡', 'color' => '#5EEAD4', 'title' => 'Isi Data Contoh', 'desc' => 'Tombol di dashboard untuk mengisi ±8 mahasiswa dan ±15 nilai dummy terenkripsi. Demo cepat!'],
        ];
        foreach ($features as $f): ?>
        <div class="feature-card fade-in">
            <div class="feature-icon" style="background:color-mix(in srgb, <?= $f['color'] ?> 15%, transparent);font-size:20px">
                <?= $f['icon'] ?>
            </div>
            <h4 style="font-size:15px;font-weight:700;margin-bottom:6px"><?= htmlspecialchars($f['title'], ENT_QUOTES) ?></h4>
            <p style="font-size:13px;color:var(--text-dim);line-height:1.6"><?= htmlspecialchars($f['desc'], ENT_QUOTES) ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Footer -->
<footer class="landing-footer">
    <p><?= APP_NAME ?> &mdash; UTS <?= htmlspecialchars(APP_COURSE, ENT_QUOTES) ?></p>
    <p style="margin-top:4px">Dibuat oleh <strong><?= htmlspecialchars(APP_AUTHOR, ENT_QUOTES) ?></strong> &bull; NIM <?= htmlspecialchars(APP_NIM, ENT_QUOTES) ?></p>
    <p style="margin-top:8px;font-family:var(--font-mono);font-size:11px;color:var(--text-muted)">AES-256-GCM + RSA-2048 OAEP | PHP <?= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ?></p>
</footer>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
