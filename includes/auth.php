<?php
/**
 * CipherCampus - Autentikasi & Guard
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/crypto.php';

// --- Konfigurasi Sesi Aman ---
function init_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) return;

    ini_set('session.use_strict_mode',   '1');
    ini_set('session.use_only_cookies',  '1');
    ini_set('session.cookie_httponly',   '1');
    ini_set('session.cookie_samesite',   'Strict');
    // https hanya di produksi
    ini_set('session.cookie_secure', APP_ENV === 'production' ? '1' : '0');

    session_start();
}

// --- Guard: wajib login ---
function require_login(): void
{
    init_session();
    check_session_timeout();

    if (empty($_SESSION['logged_in'])) {
        header('Location: ' . BASE_URL . '/login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    // Perbarui waktu aktivitas terakhir
    $_SESSION['last_active'] = time();
}

// --- Cek idle timeout ---
function check_session_timeout(): void
{
    if (!isset($_SESSION['last_active'])) return;
    if (time() - $_SESSION['last_active'] > SESSION_IDLE_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/login.php?reason=timeout');
        exit;
    }
}

// --- Cek apakah sudah login ---
function is_logged_in(): bool
{
    init_session();
    if (empty($_SESSION['logged_in'])) return false;
    if (time() - ($_SESSION['last_active'] ?? 0) > SESSION_IDLE_TIMEOUT) return false;
    return true;
}

// --- Rate limit: max 5 gagal per IP per 10 menit ---
function check_rate_limit(string $ip): bool
{
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM login_log
         WHERE ip = ? AND status = 'failed' AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
    );
    $stmt->execute([$ip]);
    return (int)$stmt->fetchColumn() < 5;
}

function log_login_attempt(string $ip, string $status): void
{
    $pdo = db();
    $pdo->prepare("INSERT INTO login_log (ip, status) VALUES (?, ?)")
        ->execute([$ip, $status]);
    // Bersihkan log lama (> 24 jam) secara periodik
    if (mt_rand(1, 20) === 1) {
        $pdo->exec("DELETE FROM login_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    }
}

function get_failed_count(string $ip): int
{
    $pdo  = db();
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM login_log
         WHERE ip = ? AND status = 'failed' AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
    );
    $stmt->execute([$ip]);
    return (int)$stmt->fetchColumn();
}

// --- Proses login dua kunci ---
function attempt_login(string $kSymHex, string $privPem): array
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (!check_rate_limit($ip)) {
        return ['ok' => false, 'error' => 'rate_limit', 'msg' => 'Terlalu banyak percobaan. Tunggu 10 menit.'];
    }

    // Ambil kunci dari DB
    $pdo  = db();
    $stmt = $pdo->query("SELECT * FROM app_keys LIMIT 1");
    $keys = $stmt->fetch();

    if (!$keys) {
        return ['ok' => false, 'error' => 'no_keys', 'msg' => 'Kunci belum di-setup.'];
    }

    $ok = false;
    try {
        $kSym = CryptoService::hexToKSym($kSymHex);

        // Cek simetris
        $symOk = CryptoService::verifySymCheck($keys['sym_check'], $kSym);

        // Cek asimetris
        $privOk = false;
        if ($symOk) {
            // Verifikasi kunci privat cocok dengan kunci publik
            $matchOk = CryptoService::privateMatchesPublic($privPem, $keys['rsa_public_key']);
            if ($matchOk) {
                $privOk = CryptoService::verifyRsaChallenge(
                    $keys['rsa_challenge_ct'],
                    $keys['rsa_challenge_hash'],
                    $privPem
                );
            }
        }

        $ok = $symOk && $privOk;
    } catch (Throwable $e) {
        error_log('Login error: ' . $e->getMessage());
    }

    if (!$ok) {
        log_login_attempt($ip, 'failed');
        return ['ok' => false, 'error' => 'invalid', 'msg' => 'Kunci tidak valid.'];
    }

    // Login berhasil
    log_login_attempt($ip, 'success');
    session_regenerate_id(true);

    // Simpan kunci di sesi (kompromi edukasi - dicatat di README)
    $_SESSION['logged_in']   = true;
    $_SESSION['k_sym_hex']   = $kSymHex;   // K_sym sebagai hex
    $_SESSION['priv_pem']    = $privPem;   // Kunci privat RSA
    $_SESSION['last_active'] = time();
    $_SESSION['login_time']  = time();

    return ['ok' => true];
}

// --- Ambil kunci dari sesi ---
function session_k_sym(): string
{
    return CryptoService::hexToKSym($_SESSION['k_sym_hex'] ?? '');
}

function session_priv_pem(): string
{
    return $_SESSION['priv_pem'] ?? '';
}

function session_pub_key(): string
{
    $pdo  = db();
    $stmt = $pdo->query("SELECT rsa_public_key FROM app_keys LIMIT 1");
    $row  = $stmt->fetch();
    return $row['rsa_public_key'] ?? '';
}

// --- CSRF Token ---
function csrf_token(): string
{
    init_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function verify_csrf(): bool
{
    init_session();
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function require_csrf(): void
{
    if (!verify_csrf()) {
        http_response_code(403);
        die(json_encode(['ok' => false, 'msg' => 'Token CSRF tidak valid.']));
    }
}

// --- Logout ---
function logout(): void
{
    init_session();
    session_unset();
    session_destroy();
    // Hapus cookie sesi
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
}

// --- Ambil sisa waktu sesi (detik) ---
function session_remaining(): int
{
    if (empty($_SESSION['last_active'])) return 0;
    $remaining = SESSION_IDLE_TIMEOUT - (time() - $_SESSION['last_active']);
    return max(0, $remaining);
}

// --- Setup headers keamanan dasar ---
function security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
}
