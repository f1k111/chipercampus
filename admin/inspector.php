<?php
/**
 * CipherCampus - Crypto Inspector
 * Visualisasi alur enkripsi/dekripsi per baris data.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/crypto.php';
require_once __DIR__ . '/../includes/layout.php';

security_headers();
require_login();

$pdo     = db();
$kSym    = session_k_sym();
$privPem = session_priv_pem();

$selectedTable = $_GET['table'] ?? 'mahasiswa';
$selectedId    = (int)($_GET['id'] ?? 0);

// Ambil daftar data
if ($selectedTable === 'nilai') {
    $list = $pdo->query("SELECT id, mahasiswa_id, dek_envelope FROM nilai ORDER BY created_at DESC LIMIT 20")->fetchAll();
} else {
    $selectedTable = 'mahasiswa';
    $list = $pdo->query("SELECT id, nim_enc, dek_envelope FROM mahasiswa ORDER BY created_at DESC LIMIT 20")->fetchAll();
}

// Inspeksi baris terpilih
$inspectData = null;
$inspectErr  = '';
$plainFields = [];

if ($selectedId > 0) {
    if ($selectedTable === 'mahasiswa') {
        $stmt = $pdo->prepare("SELECT * FROM mahasiswa WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM nilai WHERE id = ?");
    }
    $stmt->execute([$selectedId]);
    $inspectData = $stmt->fetch();

    if ($inspectData) {
        try {
            if ($selectedTable === 'mahasiswa') {
                $plain = CryptoService::decryptMahasiswa($inspectData, $privPem, $kSym);
            } else {
                $plain = CryptoService::decryptNilai($inspectData, $privPem, $kSym);
            }
            $plainFields = $plain;
        } catch (Throwable $e) {
            $inspectErr = $e->getMessage();
        }
    }
}

render_head('Crypto Inspector');
render_admin_layout_start('Crypto Inspector', 'inspector');
?>
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<p style="color:var(--text-dim);font-size:14px;margin-bottom:20px">
    Pilih baris data untuk melihat alur enkripsi lengkap: komponen IV, tag GCM, ciphertext, dan envelope DEK.
</p>

<!-- Pilih tabel & baris -->
<div class="card mb-6">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <div class="form-group" style="flex:1;min-width:140px">
            <label>Tabel</label>
            <select name="table" onchange="this.form.submit()">
                <option value="mahasiswa" <?= $selectedTable === 'mahasiswa' ? 'selected' : '' ?>>Mahasiswa</option>
                <option value="nilai" <?= $selectedTable === 'nilai' ? 'selected' : '' ?>>Nilai</option>
            </select>
        </div>
        <div class="form-group" style="flex:2;min-width:200px">
            <label>Baris Data</label>
            <select name="id">
                <option value="0">— Pilih ID —</option>
                <?php foreach ($list as $item): ?>
                <option value="<?= $item['id'] ?>" <?= $selectedId === (int)$item['id'] ? 'selected' : '' ?>>
                    ID #<?= $item['id'] ?>
                    <?php if ($selectedTable === 'mahasiswa'): ?>
                    — NIM enc: <?= htmlspecialchars(substr($item['nim_enc'], 0, 24) . '…', ENT_QUOTES) ?>
                    <?php else: ?>
                    — mhs_id: <?= $item['mahasiswa_id'] ?>
                    <?php endif; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Inspeksi</button>
    </form>
</div>

<?php if ($inspectData && !$inspectErr): ?>
<!-- Alur enkripsi visual -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
    <!-- Kolom kiri: Plaintext -->
    <div class="card">
        <h3 style="font-size:14px;font-weight:700;color:var(--accent);margin-bottom:14px;font-family:var(--font-mono)">
            → PLAINTEXT (terdekripsi)
        </h3>
        <?php foreach ($plainFields as $k => $v): ?>
        <?php if (in_array($k, ['id'])): continue; endif; ?>
        <div style="margin-bottom:10px">
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:2px"><?= htmlspecialchars($k, ENT_QUOTES) ?></div>
            <div style="font-family:var(--font-mono);font-size:13px;color:var(--text);word-break:break-all">
                <?= htmlspecialchars($v, ENT_QUOTES) ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Kolom kanan: Ciphertext fields -->
    <div class="card">
        <h3 style="font-size:14px;font-weight:700;color:var(--cipher);margin-bottom:14px;font-family:var(--font-mono)">
            ← CIPHERTEXT (di database)
        </h3>
        <?php
        $encFields = $selectedTable === 'mahasiswa'
            ? ['nim_enc','nama_enc','prodi_enc','email_enc']
            : ['matkul_enc','sks_enc','nilai_angka_enc','nilai_huruf_enc'];
        foreach ($encFields as $f):
            $val = $inspectData[$f] ?? '';
            $parts = explode(':', $val, 3);
        ?>
        <div style="margin-bottom:10px">
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:4px">
                <?= htmlspecialchars($f, ENT_QUOTES) ?>
            </div>
            <?php if (count($parts) === 3): ?>
            <div style="display:flex;flex-direction:column;gap:3px">
                <div style="font-size:11px"><span style="color:var(--accent2)">IV:</span> <span style="font-family:var(--font-mono);font-size:11px;color:var(--cipher)"><?= htmlspecialchars(bin2hex(base64_decode($parts[0])), ENT_QUOTES) ?></span> <span style="color:var(--text-muted)">(<?= strlen(base64_decode($parts[0])) ?> byte)</span></div>
                <div style="font-size:11px"><span style="color:var(--warning)">Tag:</span> <span style="font-family:var(--font-mono);font-size:11px;color:var(--cipher)"><?= htmlspecialchars(bin2hex(base64_decode($parts[1])), ENT_QUOTES) ?></span> <span style="color:var(--text-muted)">(<?= strlen(base64_decode($parts[1])) ?> byte)</span></div>
                <div style="font-size:11px"><span style="color:var(--danger)">CT:</span> <span style="font-family:var(--font-mono);font-size:11px;color:var(--cipher)"><?= htmlspecialchars(substr($parts[2], 0, 32) . '…', ENT_QUOTES) ?></span> <span style="color:var(--text-muted)">(<?= strlen(base64_decode($parts[2])) ?> byte)</span></div>
            </div>
            <?php else: ?>
            <div style="font-family:var(--font-mono);font-size:11px;color:var(--cipher)"><?= htmlspecialchars(substr($val, 0, 48), ENT_QUOTES) ?>…</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Alur DEK Envelope -->
<div class="card mb-6">
    <h3 style="font-size:14px;font-weight:700;margin-bottom:16px">Alur DEK Envelope (Pembungkus Kunci)</h3>
    <div class="inspector-flow">
        <div class="flow-step">
            <div class="flow-step-num">1</div>
            <div class="flow-step-content">
                <div class="flow-step-title">DEK (Data Encryption Key)</div>
                <div class="flow-step-meta">32 byte acak per baris — digunakan untuk AES-256-GCM semua field</div>
                <div class="flow-step-data">random_bytes(32) → DEK [tersembunyi saat ini]</div>
            </div>
        </div>
        <div class="flow-step">
            <div class="flow-step-num">2</div>
            <div class="flow-step-content">
                <div class="flow-step-title">Lapis 1: RSA-OAEP Enkripsi DEK</div>
                <div class="flow-step-meta">openssl_public_encrypt(DEK, kunci_publik, OAEP) → RSA_output (256 byte)</div>
                <div class="flow-step-data">RSA-OAEP(pub_key, DEK) → <?= strlen(base64_decode((json_decode(base64_decode($inspectData['dek_envelope']), true)['ct'] ?? ''))) ?> byte ciphertext</div>
            </div>
        </div>
        <?php
        $envelopeInfo = CryptoService::inspectEnvelope($inspectData['dek_envelope']);
        ?>
        <div class="flow-step">
            <div class="flow-step-num">3</div>
            <div class="flow-step-content">
                <div class="flow-step-title">Lapis 2: AES-256-GCM Enkripsi Hasil RSA dengan K_sym</div>
                <div class="flow-step-meta">IV: <?= $envelopeInfo['iv_len'] ?> byte | Tag: <?= $envelopeInfo['tag_len'] ?> byte | CT: <?= $envelopeInfo['ct_len'] ?> byte</div>
                <div class="flow-step-data">
                    IV:  <?= htmlspecialchars($envelopeInfo['iv_hex'], ENT_QUOTES) ?><br>
                    Tag: <?= htmlspecialchars($envelopeInfo['tag_hex'], ENT_QUOTES) ?><br>
                    CT:  <?= htmlspecialchars($envelopeInfo['ct_b64_truncated'], ENT_QUOTES) ?>
                </div>
            </div>
        </div>
        <div class="flow-step">
            <div class="flow-step-num">4</div>
            <div class="flow-step-content">
                <div class="flow-step-title">dek_envelope (tersimpan di database)</div>
                <div class="flow-step-meta">Base64(JSON{iv, tag, ct}) — total <?= $envelopeInfo['total_len'] ?> byte sebelum encoding</div>
                <div class="flow-step-data"><?= htmlspecialchars(substr($inspectData['dek_envelope'], 0, 80) . '…', ENT_QUOTES) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Verifikasi integritas -->
<div class="card">
    <h3 style="font-size:14px;font-weight:700;margin-bottom:12px">Verifikasi Integritas (GCM Tag)</h3>
    <p style="font-size:13px;color:var(--text-dim);margin-bottom:14px">
        GCM authentication tag memastikan bahwa data tidak dimodifikasi sejak dienkripsi.
        Jika satu byte ciphertext diubah, dekripsi akan gagal.
    </p>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <button class="btn btn-secondary btn-sm" onclick="verifyIntegrity()">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M7 1L2 3v4c0 2.76 2.24 5 5 5s5-2.24 5-5V3L7 1z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M5 7l1.5 1.5L9 5.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Verifikasi Semua Field
        </button>
        <div id="verifyResult" style="font-size:13px"></div>
    </div>
</div>

<script>
async function verifyIntegrity() {
    const btn = document.querySelector('[onclick="verifyIntegrity()"]');
    const result = document.getElementById('verifyResult');
    btn.disabled = true;
    result.innerHTML = '<span style="color:var(--text-dim)">Memverifikasi…</span>';

    try {
        const res = await window.CC.apiPost(`${window.BASE_URL}/api/decrypt.php`, {
            table: '<?= $selectedTable ?>',
            ids: ['<?= $selectedId ?>'],
            verify: true
        });

        if (res.ok) {
            result.innerHTML = '<span style="color:var(--success);display:flex;align-items:center;gap:5px"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.3"/><path d="M4.5 7l2 2 3-3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg> Integritas terverifikasi — semua tag GCM valid</span>';
        } else {
            result.innerHTML = `<span style="color:var(--danger)">⚠ ${res.msg}</span>`;
        }
    } catch(e) {
        result.innerHTML = '<span style="color:var(--danger)">Error jaringan</span>';
    } finally {
        btn.disabled = false;
    }
}
</script>

<?php elseif ($inspectErr): ?>
<div class="auth-error">
    <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M8 5v4M8 11v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
    <?= htmlspecialchars($inspectErr, ENT_QUOTES) ?>
</div>
<?php elseif ($selectedId > 0): ?>
<div style="text-align:center;padding:40px;color:var(--text-muted)">Data tidak ditemukan.</div>
<?php else: ?>
<div class="card" style="text-align:center;padding:40px;color:var(--text-muted)">
    <svg width="48" height="48" viewBox="0 0 48 48" fill="none" style="margin:0 auto 12px;display:block"><circle cx="21" cy="21" r="13" stroke="currentColor" stroke-width="2" opacity="0.4"/><path d="M31 31l10 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity="0.4"/></svg>
    Pilih tabel dan ID baris di atas untuk memulai inspeksi.
</div>
<?php endif; ?>

<?php render_admin_layout_end(); ?>
