/**
 * CipherCampus - JavaScript Utama
 */

'use strict';

// ============================================================
// TEMA: dark/light toggle dengan localStorage
// ============================================================
(function initTheme() {
    let theme = 'dark';
    try { theme = localStorage.getItem('cc-theme') || 'dark'; } catch (_) {}
    document.documentElement.setAttribute('data-theme', theme);
})();

function toggleTheme() {
    const html = document.documentElement;
    const cur = html.getAttribute('data-theme') || 'dark';
    const next = cur === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    try { localStorage.setItem('cc-theme', next); } catch (_) {}
}

document.addEventListener('DOMContentLoaded', () => {
    // Theme toggle
    document.querySelectorAll('#themeToggle, #themeToggleLanding').forEach(btn => {
        btn?.addEventListener('click', toggleTheme);
    });

    // Sidebar toggle (desktop)
    document.getElementById('sidebarToggle')?.addEventListener('click', () => {
        document.getElementById('sidebar')?.classList.toggle('collapsed');
    });

    // Sidebar toggle (mobile)
    document.getElementById('sidebarToggleMobile')?.addEventListener('click', () => {
        document.getElementById('sidebar')?.classList.toggle('mobile-open');
    });

    // Tutup sidebar mobile saat klik di luar
    document.addEventListener('click', (e) => {
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('sidebarToggleMobile');
        if (sidebar?.classList.contains('mobile-open') &&
            !sidebar.contains(e.target) &&
            !toggleBtn?.contains(e.target)) {
            sidebar.classList.remove('mobile-open');
        }
    });

    // Session timer
    initSessionTimer();

    // Scroll animations
    initScrollAnimations();

    // Copy buttons
    initCopyButtons();

    // File drop zones
    initFileDropZones();
});

// ============================================================
// SESSION TIMER
// ============================================================
function initSessionTimer() {
    const timerEl = document.getElementById('sessionTimer');
    const displayEl = document.getElementById('timerDisplay');
    if (!timerEl || !displayEl) return;

    let remaining = parseInt(timerEl.dataset.remaining || '0', 10);

    function update() {
        if (remaining <= 0) {
            displayEl.textContent = '00:00';
            timerEl.style.color = 'var(--danger)';
            return;
        }
        const m = Math.floor(remaining / 60);
        const s = remaining % 60;
        displayEl.textContent = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
        if (remaining <= 120) {
            timerEl.style.color = 'var(--warning)';
        }
        remaining--;
    }

    update();
    setInterval(update, 1000);
}

// ============================================================
// TOAST NOTIFIKASI
// ============================================================
function showToast(message, type = 'info', duration = 3500) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const icons = {
        success: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="#4ADE80" stroke-width="1.5"/><path d="M5.5 8l2 2 3-3" stroke="#4ADE80" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        error:   '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="#FF6B5A" stroke-width="1.5"/><path d="M6 6l4 4M10 6l-4 4" stroke="#FF6B5A" stroke-width="1.5" stroke-linecap="round"/></svg>',
        info:    '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="#5EEAD4" stroke-width="1.5"/><path d="M8 7v4M8 5.5v.5" stroke="#5EEAD4" stroke-width="1.5" stroke-linecap="round"/></svg>',
    };

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = (icons[type] || icons.info) + `<span>${message}</span>`;
    container.appendChild(toast);

    requestAnimationFrame(() => {
        requestAnimationFrame(() => toast.classList.add('show'));
    });

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 400);
    }, duration);
}

// ============================================================
// SCROLL ANIMATIONS (IntersectionObserver)
// ============================================================
function initScrollAnimations() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                // Delay staggered untuk anak-anak grid
                const delay = entry.target.dataset.delay || 0;
                setTimeout(() => {
                    entry.target.classList.add('visible');
                }, delay);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });

    document.querySelectorAll('.fade-in, .step-card').forEach((el, i) => {
        if (!el.dataset.delay) el.dataset.delay = i * 80;
        observer.observe(el);
    });
}

// ============================================================
// COPY BUTTONS
// ============================================================
function initCopyButtons() {
    document.querySelectorAll('[data-copy]').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = document.querySelector(btn.dataset.copy);
            const text = target ? target.textContent.trim() : btn.dataset.copyText || '';
            copyToClipboard(text, btn);
        });
    });
}

async function copyToClipboard(text, btn = null) {
    try {
        await navigator.clipboard.writeText(text);
        if (btn) {
            const orig = btn.innerHTML;
            btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg> Tersalin';
            btn.classList.add('copied');
            setTimeout(() => { btn.innerHTML = orig; btn.classList.remove('copied'); }, 1800);
        }
        showToast('Disalin ke clipboard!', 'success', 2000);
    } catch {
        showToast('Gagal menyalin', 'error');
    }
}

// ============================================================
// CIPHER SCRAMBLE ANIMATION
// ============================================================
const SCRAMBLE_CHARS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz+/=';

function scrambleText(el, targetText, duration = 600) {
    const steps = Math.ceil(duration / 40);
    let step = 0;
    el.style.color = 'var(--cipher)';
    el.style.fontFamily = 'var(--font-mono)';

    const interval = setInterval(() => {
        step++;
        const progress = step / steps;
        const revealCount = Math.floor(progress * targetText.length);
        let result = targetText.slice(0, revealCount);
        for (let i = revealCount; i < targetText.length; i++) {
            result += SCRAMBLE_CHARS[Math.floor(Math.random() * SCRAMBLE_CHARS.length)];
        }
        el.textContent = result;

        if (step >= steps) {
            clearInterval(interval);
            el.textContent = targetText;
            el.style.color = '';
            el.style.fontFamily = '';
        }
    }, 40);
}

function encryptText(el, originalText, duration = 400) {
    const length = originalText.length || 24;
    const steps = Math.ceil(duration / 40);
    let step = 0;
    el.style.color = 'var(--cipher)';
    el.style.fontFamily = 'var(--font-mono)';

    const interval = setInterval(() => {
        step++;
        let result = '';
        for (let i = 0; i < length; i++) {
            result += SCRAMBLE_CHARS[Math.floor(Math.random() * SCRAMBLE_CHARS.length)];
        }
        el.textContent = result;

        if (step >= steps) {
            clearInterval(interval);
        }
    }, 40);
}

// ============================================================
// HERO TEXT SCRAMBLE (Landing)
// ============================================================
function initHeroScramble() {
    const el = document.getElementById('heroCipherText');
    if (!el) return;

    const words = ['DATA TERENKRIPSI', 'AES-256-GCM', 'RSA-2048', 'HYBRID CRYPTO'];
    let idx = 0;

    function cycle() {
        idx = (idx + 1) % words.length;
        scrambleText(el, words[idx], 700);
    }

    setInterval(cycle, 2800);
}

document.addEventListener('DOMContentLoaded', initHeroScramble);

// ============================================================
// FILE DROP ZONE
// ============================================================
function initFileDropZones() {
    document.querySelectorAll('.file-drop').forEach(zone => {
        const input = zone.querySelector('input[type="file"]');
        if (!input) return;

        zone.addEventListener('click', () => input.click());
        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.classList.remove('drag-over');
            const file = e.dataTransfer.files[0];
            if (file) handlePemFile(file, zone, input);
        });

        input.addEventListener('change', () => {
            if (input.files[0]) handlePemFile(input.files[0], zone, input);
        });
    });
}

function handlePemFile(file, zone, input) {
    if (!file.name.endsWith('.pem') && file.type !== 'application/x-pem-file') {
        showToast('Hanya file .pem yang diterima', 'error');
        return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        const content = e.target.result;
        const targetId = zone.dataset.target;
        if (targetId) {
            const textarea = document.getElementById(targetId);
            if (textarea) {
                textarea.value = content;
                textarea.dispatchEvent(new Event('input'));
            }
        }

        zone.classList.add('has-file');
        const label = zone.querySelector('.drop-label');
        if (label) label.textContent = `✓ ${file.name}`;
        showToast('File kunci privat dimuat', 'success');
    };
    reader.readAsText(file);
}

// ============================================================
// MODAL HELPER
// ============================================================
function openModal(id) {
    const backdrop = document.getElementById(id);
    if (!backdrop) return;
    backdrop.classList.add('show');
    document.body.style.overflow = 'hidden';
    backdrop.querySelector('[autofocus]')?.focus();
}

function closeModal(id) {
    const backdrop = typeof id === 'string' ? document.getElementById(id) : id;
    if (!backdrop) return;
    backdrop.classList.remove('show');
    document.body.style.overflow = '';
}

// Tutup modal saat klik backdrop
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-backdrop')) closeModal(e.target);
});

// Tutup modal dengan Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-backdrop.show').forEach(closeModal);
    }
});

// Tombol tutup modal
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-close-modal]');
    if (btn) closeModal(btn.dataset.closeModal || btn.closest('.modal-backdrop'));
});

// ============================================================
// CONFIRM MODAL (pengganti alert/confirm)
// ============================================================
function confirmDialog(message, title = 'Konfirmasi') {
    return new Promise((resolve) => {
        const id = 'confirmModal-' + Date.now();
        const el = document.createElement('div');
        el.id = id;
        el.className = 'modal-backdrop';
        el.innerHTML = `
            <div class="modal" style="max-width:400px">
                <div class="modal-header">
                    <h3 class="modal-title">${title}</h3>
                </div>
                <p style="color:var(--text-dim);font-size:14px;margin-bottom:20px">${message}</p>
                <div class="flex gap-3 justify-end">
                    <button class="btn btn-secondary" id="${id}-no">Batal</button>
                    <button class="btn btn-danger" id="${id}-yes">Ya, Hapus</button>
                </div>
            </div>`;
        document.body.appendChild(el);
        requestAnimationFrame(() => el.classList.add('show'));

        document.getElementById(`${id}-yes`).onclick = () => { closeModal(el); el.remove(); resolve(true); };
        document.getElementById(`${id}-no`).onclick  = () => { closeModal(el); el.remove(); resolve(false); };
    });
}

// ============================================================
// CSRF TOKEN (dari meta tag)
// ============================================================
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

// ============================================================
// API HELPER (fetch dengan CSRF)
// ============================================================
async function apiPost(url, data) {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': getCsrfToken(),
        },
        body: JSON.stringify(data),
    });
    return res.json();
}

// ============================================================
// DEKRIPSI ON-DEMAND (tabel mahasiswa/nilai)
// ============================================================
let decryptedCache = {}; // id -> data

async function toggleDecryptView(btn) {
    const table = btn.closest('[data-table]');
    if (!table) return;
    const tableName = table.dataset.table;
    const isPlain = btn.dataset.mode === 'cipher';

    btn.dataset.mode = isPlain ? 'plain' : 'cipher';
    btn.textContent = isPlain ? 'Tampilkan Plaintext' : 'Tampilkan Ciphertext';

    const rows = table.querySelectorAll('tr[data-id]');

    if (!isPlain) {
        // Balik ke ciphertext
        rows.forEach(row => {
            row.querySelectorAll('[data-plain-field]').forEach(cell => {
                const origEl = cell.querySelector('.cipher-orig');
                const plainEl = cell.querySelector('.plain-value');
                if (origEl) origEl.style.display = '';
                if (plainEl) plainEl.remove();
            });
        });
        return;
    }

    // Ambil data plaintext
    const ids = Array.from(rows).map(r => r.dataset.id);

    // Loading skeletons
    rows.forEach(row => {
        row.querySelectorAll('[data-plain-field]').forEach(cell => {
            const origEl = cell.querySelector('.cipher-orig');
            if (origEl) origEl.style.display = 'none';
            let skEl = cell.querySelector('.plain-value');
            if (!skEl) {
                skEl = document.createElement('span');
                skEl.className = 'plain-value skeleton';
                skEl.style.width = '100px';
                skEl.style.display = 'inline-block';
                cell.appendChild(skEl);
            }
        });
    });

    try {
        const result = await apiPost(`${window.BASE_URL}/api/decrypt.php`, {
            table: tableName,
            ids: ids,
        });

        if (!result.ok) {
            showToast('Gagal dekripsi: ' + result.msg, 'error');
            return;
        }

        // Render hasil
        rows.forEach(row => {
            const id = row.dataset.id;
            const data = result.data[id];
            if (!data) return;

            row.querySelectorAll('[data-plain-field]').forEach(cell => {
                const field = cell.dataset.plainField;
                const val = data[field] ?? '—';
                let plainEl = cell.querySelector('.plain-value');
                if (plainEl) {
                    plainEl.className = 'plain-value td-plain decrypt-anim';
                    plainEl.textContent = '';
                    scrambleText(plainEl, val, 500);
                }
            });
        });

    } catch (err) {
        showToast('Error jaringan', 'error');
        console.error(err);
    }
}

// Ekspor fungsi global
window.CC = {
    openModal,
    closeModal,
    confirmDialog,
    showToast,
    copyToClipboard,
    toggleDecryptView,
    scrambleText,
    getCsrfToken,
    apiPost,
};
