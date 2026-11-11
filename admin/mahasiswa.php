<?php
/**
 * CipherCampus - CRUD Mahasiswa
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
$pubKey  = session_pub_key();

$action = $_GET['action'] ?? 'list';
$error  = '';
$success = '';

// --- HAPUS ---
if ($action === 'delete' && isset($_GET['id'])) {
    if (!verify_csrf()) { $error = 'Token CSRF tidak valid.'; $action = 'list'; }
    else {
        $id = (int)$_GET['id'];
        $pdo->prepare("DELETE FROM mahasiswa WHERE id = ?")->execute([$id]);
        header('Location: ' . BASE_URL . '/admin/mahasiswa.php?msg=deleted');
        exit;
    }
}

// --- SIMPAN (tambah/ubah) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Token CSRF tidak valid.';
    } else {
        $nim    = trim($_POST['nim']   ?? '');
        $nama   = trim($_POST['nama']  ?? '');
        $prodi  = trim($_POST['prodi'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $editId = (int)($_POST['edit_id'] ?? 0);

        // Validasi
        if (!preg_match('/^\d{5,15}$/', $nim)) {
            $error = 'NIM harus berupa angka 5-15 digit.';
        } elseif (strlen($nama) < 2 || strlen($nama) > 100) {
            $error = 'Nama harus 2-100 karakter.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email tidak valid.';
        } else {
            try {
                $nimHash = CryptoService::hmacNim($nim, $kSym);

                // Cek duplikat NIM (kecuali dirinya sendiri saat edit)
                $stmt = $pdo->prepare("SELECT id FROM mahasiswa WHERE nim_hash = ? AND id != ?");
                $stmt->execute([$nimHash, $editId]);
                if ($stmt->fetch()) {
                    $error = 'NIM sudah terdaftar.';
                } else {
                    $enc = CryptoService::encryptMahasiswa(
                        ['nim' => $nim, 'nama' => $nama, 'prodi' => $prodi, 'email' => $email],
                        $pubKey, $kSym
                    );

                    if ($editId > 0) {
                        $pdo->prepare(
                            "UPDATE mahasiswa SET nim_hash=?, nim_enc=?, nama_enc=?, prodi_enc=?, email_enc=?, dek_envelope=? WHERE id=?"
                        )->execute([
                            $enc['nim_hash'], $enc['nim_enc'], $enc['nama_enc'],
                            $enc['prodi_enc'], $enc['email_enc'], $enc['dek_envelope'], $editId
                        ]);
                        header('Location: ' . BASE_URL . '/admin/mahasiswa.php?msg=updated');
                    } else {
                        $pdo->prepare(
                            "INSERT INTO mahasiswa (nim_hash, nim_enc, nama_enc, prodi_enc, email_enc, dek_envelope) VALUES (?,?,?,?,?,?)"
                        )->execute([
                            $enc['nim_hash'], $enc['nim_enc'], $enc['nama_enc'],
                            $enc['prodi_enc'], $enc['email_enc'], $enc['dek_envelope']
                        ]);
                        header('Location: ' . BASE_URL . '/admin/mahasiswa.php?msg=added');
                    }
                    exit;
                }
            } catch (Throwable $e) {
                $error = 'Enkripsi gagal: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES);
            }
        }
        $action = $editId > 0 ? 'edit' : 'add';
    }
}

// --- Ambil data untuk edit ---
$editRow = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM mahasiswa WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
    if (!$editRow) { header('Location: ' . BASE_URL . '/admin/mahasiswa.php'); exit; }
}

// --- Paginasi & List ---
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 10;
$offset   = ($page - 1) * $perPage;
$total    = (int)$pdo->query("SELECT COUNT(*) FROM mahasiswa")->fetchColumn();
$pages    = (int)ceil($total / $perPage);
$rows     = $pdo->prepare("SELECT * FROM mahasiswa ORDER BY created_at DESC LIMIT ? OFFSET ?");
$rows->execute([$perPage, $offset]);
$rows = $rows->fetchAll();

$msgMap = [
    'added'   => ['Mahasiswa berhasil ditambahkan!', 'success'],
    'updated' => ['Data mahasiswa berhasil diperbarui!', 'success'],
    'deleted' => ['Mahasiswa berhasil dihapus.', 'info'],
];
$msg = $msgMap[$_GET['msg'] ?? ''] ?? null;

render_head('Mahasiswa');
render_admin_layout_start('Data Mahasiswa', 'mahasiswa');
?>
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<?php if ($msg): ?>
<div id="pageMsg" data-msg="<?= htmlspecialchars($msg[0], ENT_QUOTES) ?>" data-type="<?= $msg[1] ?>"></div>
<?php endif; ?>

<!-- Header aksi -->
<div class="flex items-center justify-between mb-6">
    <div>
        <p style="color:var(--text-dim);font-size:13px"><?= $total ?> mahasiswa terdaftar</p>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
        <!-- Toggle ciphertext/plaintext -->
        <label class="toggle-wrap" title="Toggle tampilan ciphertext/plaintext">
            <div class="toggle-switch">
                <input type="checkbox" id="decryptToggle">
                <span class="toggle-track"></span>
            </div>
            <span id="toggleLabel" style="font-size:13px;color:var(--text-dim)">Plaintext</span>
        </label>
        <button class="btn btn-primary btn-sm" onclick="CC.openModal('modalTambah')">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M7 2v10M2 7h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            Tambah
        </button>
    </div>
</div>

<!-- Tabel -->
<div class="table-wrap" id="mahasiswaTable" data-table="mahasiswa">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>NIM <span class="badge badge-cipher" style="vertical-align:middle;font-size:10px">enc</span></th>
                <th>Nama <span class="badge badge-cipher" style="vertical-align:middle;font-size:10px">enc</span></th>
                <th>Prodi <span class="badge badge-cipher" style="vertical-align:middle;font-size:10px">enc</span></th>
                <th>Email <span class="badge badge-cipher" style="vertical-align:middle;font-size:10px">enc</span></th>
                <th>Dibuat</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $i => $row): ?>
            <tr data-id="<?= $row['id'] ?>">
                <td style="color:var(--text-muted);font-family:var(--font-mono);font-size:12px"><?= $offset + $i + 1 ?></td>
                <td data-plain-field="nim">
                    <span class="cipher-orig td-cipher" title="<?= htmlspecialchars($row['nim_enc'], ENT_QUOTES) ?>">
                        <?= htmlspecialchars(substr($row['nim_enc'], 0, 32) . '…', ENT_QUOTES) ?>
                    </span>
                </td>
                <td data-plain-field="nama">
                    <span class="cipher-orig td-cipher" title="<?= htmlspecialchars($row['nama_enc'], ENT_QUOTES) ?>">
                        <?= htmlspecialchars(substr($row['nama_enc'], 0, 32) . '…', ENT_QUOTES) ?>
                    </span>
                </td>
                <td data-plain-field="prodi">
                    <span class="cipher-orig td-cipher" title="<?= htmlspecialchars($row['prodi_enc'], ENT_QUOTES) ?>">
                        <?= htmlspecialchars(substr($row['prodi_enc'], 0, 24) . '…', ENT_QUOTES) ?>
                    </span>
                </td>
                <td data-plain-field="email">
                    <span class="cipher-orig td-cipher" title="<?= htmlspecialchars($row['email_enc'], ENT_QUOTES) ?>">
                        <?= htmlspecialchars(substr($row['email_enc'], 0, 24) . '…', ENT_QUOTES) ?>
                    </span>
                </td>
                <td style="font-size:12px;color:var(--text-dim)"><?= htmlspecialchars(date('d/m/y', strtotime($row['created_at'])), ENT_QUOTES) ?></td>
                <td class="td-actions">
                    <div style="display:flex;gap:6px">
                        <a href="?action=edit&id=<?= $row['id'] ?>" class="btn btn-ghost btn-sm" title="Edit">
                            <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M9.5 1.5l2 2L4 11H2V9l7.5-7.5z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg>
                        </a>
                        <a href="?action=delete&id=<?= $row['id'] ?>&csrf_token=<?= urlencode(csrf_token()) ?>"
                           class="btn btn-danger btn-sm" title="Hapus"
                           onclick="return handleDelete(event, <?= $row['id'] ?>)">
                            <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M2 3.5h9M5 3.5V2h3v1.5M4 3.5v7h5v-7" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
            <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">Belum ada data. <a href="#" onclick="CC.openModal('modalTambah');return false">Tambah mahasiswa</a>.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Paginasi -->
<?php if ($pages > 1): ?>
<div class="pagination mt-4">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
    <a href="?page=<?= $p ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<!-- Modal Tambah -->
<div class="modal-backdrop" id="modalTambah" <?= ($action === 'add' && $error) ? 'style="opacity:1;pointer-events:auto"' : '' ?>>
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Tambah Mahasiswa</h3>
            <button class="modal-close" data-close-modal="modalTambah" aria-label="Tutup">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M5 5l8 8M13 5l-8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            </button>
        </div>
        <?php if ($action === 'add' && $error): ?>
        <div class="auth-error" style="margin-bottom:16px"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
        <?php endif; ?>
        <form method="POST" class="auth-form">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="add_nim">NIM</label>
                <input type="text" id="add_nim" name="nim" placeholder="1234567890" required maxlength="15" value="<?= htmlspecialchars($_POST['nim'] ?? '', ENT_QUOTES) ?>">
            </div>
            <div class="form-group">
                <label for="add_nama">Nama Lengkap</label>
                <input type="text" id="add_nama" name="nama" placeholder="Nama Mahasiswa" required value="<?= htmlspecialchars($_POST['nama'] ?? '', ENT_QUOTES) ?>">
            </div>
            <div class="form-group">
                <label for="add_prodi">Program Studi</label>
                <select id="add_prodi" name="prodi" required>
                    <option value="">Pilih Prodi</option>
                    <?php foreach (['Teknik Informatika','Sistem Informasi','Ilmu Komputer','Teknik Komputer','Data Science'] as $p): ?>
                    <option value="<?= $p ?>" <?= ($_POST['prodi'] ?? '') === $p ? 'selected' : '' ?>><?= $p ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="add_email">Email</label>
                <input type="email" id="add_email" name="email" placeholder="mahasiswa@email.com" required value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) ?>">
            </div>
            <button type="submit" class="btn btn-primary w-full">Simpan & Enkripsi</button>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<?php if ($editRow): ?>
<div class="modal-backdrop" id="modalEdit" style="opacity:1;pointer-events:auto">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Edit Mahasiswa</h3>
            <a href="<?= BASE_URL ?>/admin/mahasiswa.php" class="modal-close" aria-label="Tutup">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M5 5l8 8M13 5l-8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            </a>
        </div>
        <?php if ($action === 'edit' && $error): ?>
        <div class="auth-error" style="margin-bottom:16px"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
        <?php endif; ?>
        <div class="auth-error" style="margin-bottom:16px;background:var(--accent2-dim);border-color:var(--accent2);color:var(--accent2)">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.3"/><path d="M7 5v3M7 10v.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
            Form dekripsi data saat ini diperlukan. Dekripsi berjalan di server saat submit—data lama dienkripsi ulang.
        </div>
        <form method="POST" class="auth-form" id="editForm">
            <?= csrf_field() ?>
            <input type="hidden" name="edit_id" value="<?= $editRow['id'] ?>">
            <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px">ID #<?= $editRow['id'] ?> — isi form dengan data baru (atau dekripsi dulu via inspector).</p>
            <div class="form-group">
                <label>NIM</label>
                <input type="text" name="nim" placeholder="NIM baru" required maxlength="15">
            </div>
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="nama" placeholder="Nama baru" required>
            </div>
            <div class="form-group">
                <label>Program Studi</label>
                <select name="prodi" required>
                    <option value="">Pilih Prodi</option>
                    <?php foreach (['Teknik Informatika','Sistem Informasi','Ilmu Komputer','Teknik Komputer','Data Science'] as $p): ?>
                    <option value="<?= $p ?>"><?= $p ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="Email baru" required>
            </div>
            <div style="display:flex;gap:8px">
                <a href="<?= BASE_URL ?>/admin/mahasiswa.php" class="btn btn-ghost w-full">Batal</a>
                <button type="submit" class="btn btn-primary w-full">Perbarui & Enkripsi Ulang</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php render_admin_layout_end(); ?>
<script>
// Pesan flash dari URL
document.addEventListener('DOMContentLoaded', () => {
    const msgEl = document.getElementById('pageMsg');
    if (msgEl) {
        window.CC.showToast(msgEl.dataset.msg, msgEl.dataset.type, 3000);
    }

    // Auto-buka modal tambah dari URL ?action=add
    <?php if ($action === 'add' && !$error): ?>
    window.CC.openModal('modalTambah');
    <?php endif; ?>

    // Toggle dekripsi
    const toggle = document.getElementById('decryptToggle');
    const label  = document.getElementById('toggleLabel');
    const table  = document.getElementById('mahasiswaTable');

    if (toggle) {
        toggle.addEventListener('change', () => {
            const isPlain = toggle.checked;
            label.textContent = isPlain ? 'Plaintext' : 'Plaintext';

            if (isPlain) {
                // Dekripsi on-demand
                decryptTable('mahasiswa');
            } else {
                // Balik ke ciphertext
                table.querySelectorAll('[data-plain-field]').forEach(cell => {
                    const orig = cell.querySelector('.cipher-orig');
                    const plain = cell.querySelector('.plain-value');
                    if (orig) orig.style.display = '';
                    if (plain) plain.remove();
                });
            }
        });
    }
});

async function decryptTable(tableName) {
    const table = document.getElementById('mahasiswaTable');
    const rows  = table.querySelectorAll('tr[data-id]');
    const ids   = Array.from(rows).map(r => r.dataset.id);

    // Loading
    rows.forEach(row => {
        row.querySelectorAll('[data-plain-field]').forEach(cell => {
            const orig = cell.querySelector('.cipher-orig');
            if (orig) orig.style.display = 'none';
            if (!cell.querySelector('.plain-value')) {
                const sk = document.createElement('span');
                sk.className = 'plain-value skeleton';
                sk.style.cssText = 'width:80px;height:1em;display:inline-block;border-radius:4px';
                cell.appendChild(sk);
            }
        });
    });

    try {
        const res = await window.CC.apiPost(`${window.BASE_URL}/api/decrypt.php`, { table: tableName, ids });
        if (!res.ok) { window.CC.showToast('Dekripsi gagal: ' + res.msg, 'error'); return; }

        rows.forEach(row => {
            const id   = row.dataset.id;
            const data = res.data[id];
            if (!data) return;
            row.querySelectorAll('[data-plain-field]').forEach(cell => {
                const field = cell.dataset.plainField;
                const val   = data[field] ?? '—';
                let el = cell.querySelector('.plain-value');
                if (el) {
                    el.className = 'plain-value td-plain';
                    el.textContent = '';
                    window.CC.scrambleText(el, val, 500);
                }
            });
        });
    } catch(e) {
        window.CC.showToast('Error jaringan', 'error');
    }
}

async function handleDelete(e, id) {
    e.preventDefault();
    const ok = await window.CC.confirmDialog('Hapus mahasiswa ini beserta semua nilai terkait?', 'Hapus Mahasiswa');
    if (ok) window.location.href = e.currentTarget.href;
    return false;
}
</script>
