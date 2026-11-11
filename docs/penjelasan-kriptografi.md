# Penjelasan Kriptografi CipherCampus

## 1. Algoritma yang Dipakai

| Komponen | Algoritma | Alasan |
|---|---|---|
| Enkripsi data (field) | AES-256-GCM | Simetris, cepat, authenticated encryption (GCM tag menjamin integritas) |
| Enkripsi DEK | RSA-2048 OAEP | Asimetris; memungkinkan enkripsi tanpa kunci privat di server |
| Pembungkus DEK | AES-256-GCM (K_sym) | Lapis kedua—kedua kunci wajib untuk membuka data |
| Blind index NIM | HMAC-SHA256 | Cek duplikat tanpa menyimpan NIM plaintext |
| Verifikasi login sym | AES-256-GCM | Membuktikan K_sym benar tanpa simpan K_sym di DB |
| Verifikasi login RSA | RSA-OAEP Challenge-Response | Membuktikan kepemilikan kunci privat |

## 2. Alur Enkripsi (Simpan Data)

```
Input: nim, nama, prodi, email (plaintext)

Langkah 1: Generate DEK (Data Encryption Key)
  DEK = random_bytes(32)  ← 256 bit, unik per baris

Langkah 2: Enkripsi setiap field dengan AES-256-GCM
  Untuk setiap field:
    IV = random_bytes(12)   ← IV acak, TIDAK pernah diulang
    ciphertext, tag = AES_256_GCM_Encrypt(key=DEK, iv=IV, plaintext=field)
    simpan sebagai: base64(IV) + ":" + base64(tag) + ":" + base64(ciphertext)

Langkah 3: Bungkus DEK dengan RSA-OAEP (Lapis 1)
  rsa_ct = RSA_OAEP_Encrypt(public_key, DEK)
  Padding PKCS1-OAEP (SHA-1 — bawaan PHP)

Langkah 4: Bungkus hasil RSA dengan AES-256-GCM (Lapis 2)
  IV2 = random_bytes(12)
  ct2, tag2 = AES_256_GCM_Encrypt(key=K_sym, iv=IV2, plaintext=rsa_ct)
  dek_envelope = base64(JSON{iv: IV2, tag: tag2, ct: ct2})

Langkah 5: Simpan ke database
  nim_hash    = HMAC_SHA256(derived_key, NIM)  ← blind index
  nim_enc     = IV:tag:ciphertext
  nama_enc    = IV:tag:ciphertext
  prodi_enc   = IV:tag:ciphertext
  email_enc   = IV:tag:ciphertext
  dek_envelope = base64(JSON{...})
```

## 3. Alur Dekripsi (Baca Data)

```
Input: baris dari database, K_sym (dari sesi), kunci privat RSA (dari sesi)

Langkah 1: Buka dek_envelope lapis 2 (AES dengan K_sym)
  json = base64_decode(dek_envelope)
  rsa_ct = AES_256_GCM_Decrypt(key=K_sym, iv=json.iv, tag=json.tag, ct=json.ct)
  → Jika K_sym salah: GAGAL (tag GCM tidak cocok)

Langkah 2: Buka lapis 1 RSA dengan kunci privat
  DEK = RSA_OAEP_Decrypt(private_key, rsa_ct)
  → Jika kunci privat salah: GAGAL

Langkah 3: Dekripsi setiap field dengan DEK
  Untuk setiap field (mis. nama_enc):
    split "IV:tag:ct"
    plaintext = AES_256_GCM_Decrypt(key=DEK, iv=IV, tag=tag, ct=ct)
    → Jika ciphertext dimodifikasi manual di DB: GAGAL (tag GCM tidak cocok)
```

## 4. Alur Login Dua Kunci

```
Admin input:
  1. K_sym (64 karakter hex)
  2. Kunci privat RSA (PEM)

Server verifikasi:
  Cek Simetris:
    sym_check = AES_256_GCM(K_sym, "CIPHERCAMPUS-SYM-OK")
    coba dekripsi sym_check dengan K_sym yang diinput
    berhasil? → K_sym benar

  Cek Asimetris:
    rsa_challenge_ct = RSA_OAEP(pub_key, nonce_32byte)
    rsa_challenge_hash = SHA256(nonce)
    coba dekripsi rsa_challenge_ct dengan privat yang diinput
    hash(hasil) == rsa_challenge_hash? → kunci privat benar

  Login berhasil hanya jika KEDUA cek lolos.
  Pesan error: "Kunci tidak valid" (tidak bocorkan kunci mana yang salah)
```

## 5. Properti Keamanan

- **Confidentiality**: Data tidak bisa dibaca tanpa kedua kunci (K_sym DAN kunci privat)
- **Integrity**: GCM authentication tag mendeteksi modifikasi data di database
- **Freshness/Uniqueness**: IV acak per field memastikan enkripsi dua field yang sama menghasilkan ciphertext berbeda
- **Separation of Concerns**: Kunci publik di server, kunci privat hanya di tangan admin

## 6. Keterbatasan Teknis (Jujur)

1. **RSA-OAEP SHA-1**: PHP `openssl_private_decrypt` dengan `OPENSSL_PKCS1_OAEP_PADDING` menggunakan SHA-1 secara default. SHA-256 lebih dianjurkan di produksi tetapi membutuhkan patch PHP atau library tambahan.

2. **Kunci di `$_SESSION`**: K_sym dan kunci privat RSA disimpan di session PHP selama sesi aktif. Ini kompromi untuk kemudahan penggunaan; di sistem produksi, kunci privat seharusnya tidak pernah berada di memori server.

3. **Enkripsi deterministik untuk blind index**: HMAC NIM adalah deterministik—penyerang dengan akses DB yang mengetahui NIM tertentu dapat mengkonfirmasi apakah NIM tersebut ada. Ini tradeoff antara fungsionalitas (cek duplikat) vs privasi.

4. **RSA-2048**: Sudah cukup aman untuk jangka pendek, namun RSA-4096 atau ECIES lebih dianjurkan untuk data jangka panjang.

## 7. Screenshot yang Disarankan untuk Laporan

1. `index.php` — Landing page dengan animasi scramble
2. `setup.php` — Halaman generate kunci (dengan K_sym dan PEM terlihat)
3. `login.php` — Form login dua kunci
4. `admin/dashboard.php` — Dashboard dengan statistik
5. Tabel mahasiswa dalam mode **ciphertext** (default)
6. Tabel mahasiswa dalam mode **plaintext** (setelah toggle)
7. phpMyAdmin — tabel `mahasiswa` menunjukkan kolom tak terbaca
8. `admin/inspector.php` — Alur enkripsi visual per baris
