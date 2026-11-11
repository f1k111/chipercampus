<?php
/**
 * CipherCampus - Dashboard Admin
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

security_headers();
require_login();

$pdo = db();

// Statistik
$totalMahasiswa = (int)$pdo->query("SELECT COUNT(*) FROM mahasiswa")->fetchColumn();
$totalNilai     = (int)$pdo->query("SELECT COUNT(*) FROM nilai")->fetchColumn();
$failedToday    = (int)$pdo->query(
    "SELECT COUNT(*) FROM login_log WHERE status='failed' AND created_at >= CURDATE()"
)->fetchColumn();
$lastLogin = $pdo->query(
    "SELECT created_at FROM login_log WHERE status='success' ORDER BY created_at DESC LIMIT 1 OFFSET 1"
)->fetchColumn();

render_head('Dashboard');
render_admin_layout_start('Dashboard', 'dashboard');
?>
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<!-- Stats grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Mahasiswa</div>
        <div class="stat-value" style="color:var(--accent)"><?= $totalMahasiswa ?></div>
        <div class="stat-sub">data terenkripsi</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Nilai</div>
        <div class="stat-value" style="color:var(--accent2)"><?= $totalNilai ?></div>
        <div class="stat-sub">record terenkripsi</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Login Gagal Hari Ini</div>
        <div class="stat-value" style="color:<?= $failedToday > 0 ? 'var(--danger)' : 'var(--success)' ?>"><?= $failedToday ?></div>
        <div class="stat-sub">percobaan</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Login Terakhir</div>
        <div class="stat-value" style="font-size:16px;color:var(--text-dim)">
            <?= $lastLogin ? htmlspecialchars(date('d/m H:i', strtotime($lastLogin)), ENT_QUOTES) : '—' ?>
        </div>
        <div class="stat-sub">sebelum ini</div>
    </div>
</div>

<!-- Aksi cepat -->
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px">
    <a href="<?= BASE_URL ?>/admin/mahasiswa.php?action=add" class="btn btn-primary">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="6" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M2 14c0-3.314 2.686-6 6-6s6 2.686 6 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M13 1v4M11 3h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        Tambah Mahasiswa
    </a>
    <a href="<?= BASE_URL ?>/admin/nilai.php?action=add" class="btn btn-secondary">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 12l3-3 2 2 4-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><rect x="1" y="1" width="14" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M13 1v4M11 3h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        Tambah Nilai
    </a>
    <button class="btn btn-secondary" onclick="seedData()" id="seedBtn">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M8 5v6M5 8h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        Isi Data Contoh
    </button>
    <a href="<?= BASE_URL ?>/admin/inspector.php" class="btn btn-ghost">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="4.5" stroke="currentColor" stroke-width="1.5"/><path d="M11 11l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        Crypto Inspector
    </button>
</div>

<!-- Info enkripsi -->
<div class="card" style="margin-bottom:24px">
    <h3 style="font-size:15px;font-weight:700;margin-bottom:12px">Status Enkripsi Sesi</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;font-size:13px">
        <div>
            <div style="color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:3px">K_sym (K) dimuat</div>
            <div style="color:var(--success);font-weight:600;display:flex;align-items:center;gap:6px">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.3"/><path d="M4.5 7l2 2 3-3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Ya — sesi aktif
            </div>
        </div>
        <div>
            <div style="color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:3px">Kunci privat dimuat</div>
            <div style="color:var(--success);font-weight:600;display:flex;align-items:center;gap:6px">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.3"/><path d="M4.5 7l2 2 3-3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Ya — di memori sesi
            </div>
        </div>
        <div>
            <div style="color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:3px">Algoritma</div>
            <div style="font-family:var(--font-mono);color:var(--cipher)">AES-256-GCM + RSA-2048</div>
        </div>
        <div>
            <div style="color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:3px">Idle timeout</div>
            <div style="font-family:var(--font-mono)">15 menit</div>
        </div>
    </div>
</div>

<!-- Tabel mahasiswa terbaru -->
<?php if ($totalMahasiswa > 0):
    $rows = $pdo->query("SELECT id, nim_hash, nim_enc, created_at FROM mahasiswa ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>
<div class="card">
    <div class="flex items-center justify-between mb-4">
        <h3 style="font-size:15px;font-weight:700">Data Mahasiswa Terbaru</h3>
        <a href="<?= BASE_URL ?>/admin/mahasiswa.php" class="btn btn-ghost btn-sm">Lihat Semua →</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>NIM (Ciphertext)</th>
                    <th>Ditambahkan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td style="font-family:var(--font-mono);font-size:12px;color:var(--text-muted)">#<?= $row['id'] ?></td>
                    <td class="td-cipher" title="<?= htmlspecialchars($row['nim_enc'], ENT_QUOTES) ?>">
                        <?= htmlspecialchars(substr($row['nim_enc'], 0, 40) . '...', ENT_QUOTES) ?>
                    </td>
                    <td style="font-size:12px;color:var(--text-dim)"><?= htmlspecialchars(date('d M Y H:i', strtotime($row['created_at'])), ENT_QUOTES) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php
render_admin_layout_end();
?>
<script>
async function seedData() {
    const btn = document.getElementById('seedBtn');
    const confirmed = await window.CC.confirmDialog(
        'Ini akan menambahkan ±8 mahasiswa dan ±15 nilai dummy (data fiktif, terenkripsi). Lanjutkan?',
        'Isi Data Contoh'
    );
    if (!confirmed) return;

    btn.disabled = true;
    btn.textContent = 'Memproses...';

    try {
        const res = await window.CC.apiPost(`${window.BASE_URL}/api/seed.php`, {});
        if (res.ok) {
            window.CC.showToast(`Berhasil! ${res.msg}`, 'success', 4000);
            setTimeout(() => location.reload(), 1500);
        } else {
            window.CC.showToast('Gagal: ' + res.msg, 'error');
        }
    } catch (e) {
        window.CC.showToast('Error jaringan', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Isi Data Contoh';
    }
}
</script>
