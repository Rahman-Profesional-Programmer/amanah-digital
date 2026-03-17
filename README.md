# AMANAH Digital
**Sistem Aduan & Aspirasi Orang Tua — Yayasan Ihsanul Amal Alabio**

Aplikasi web berbasis PHP untuk menampung masukan, aduan, dan aspirasi orang tua/wali murid secara **anonim dan aman**, dilengkapi dashboard admin untuk monitoring dan analitik.

---

## Fitur Utama

### Landing Page (Publik)
- Form pengiriman masukan **anonim** — tidak memerlukan login
- Pilih **Topik** dan **Sub-Topik** secara dinamis
- **Rate limiting** berbasis cookie: satu masukan per sesi untuk mencegah spam
- Tampilan responsif, dark mode / light mode otomatis
- Notifikasi real-time menggunakan SweetAlert2

### Dashboard Admin
- Login aman dengan **bcrypt password hash**
- **Filter periode** (rentang tanggal bebas)
- **Ringkasan statistik**: total masukan, IP unik, total topik
- **Grafik donat** distribusi topik pada periode terpilih
- **Grafik pie per-topik** menampilkan persentase tiap sub-topik berdasarkan data feedback
- **Tabel masukan** dengan paginasi (20 per halaman)
- **Kelola Topik & Sub-Topik**: tambah / hapus topik dan sub-topik secara langsung (tanpa reload)
- **Export PDF**: cetak laporan lengkap (header lembaga, grafik, seluruh daftar masukan) langsung dari browser

---

## Struktur Proyek

```
amanah-digital/
├── index.php                # Landing page (form masukan publik)
├── schema.sql               # Skema database MySQL/MariaDB
├── .env                     # Konfigurasi rahasia (TIDAK di-commit)
├── .env-example             # Template .env
├── config/
│   ├── db.php               # Koneksi PDO ke database
│   └── env.php              # Loader variabel dari .env
├── admin/
│   ├── login.php            # Halaman login admin
│   ├── dashboard.php        # Dashboard utama
│   ├── logout.php           # Handler logout
│   ├── auth.php             # Helper session & autentikasi
│   └── api/
│       └── manage-tags.php  # API CRUD topik & sub-topik
├── api/
│   ├── submit-feedback.php  # API kirim masukan
│   ├── get-tags.php         # API ambil daftar topik
│   └── check-eligibility.php# API cek rate limit cookie
└── assets/                  # Gambar & aset statis
```

---

## Persyaratan

| Komponen | Versi minimal |
|---|---|
| PHP | 8.1 |
| MySQL / MariaDB | 10.4 |
| Web server | Apache / Nginx (XAMPP / LAMPP) |
| Browser | Chrome / Firefox / Edge terkini |

---

## Instalasi

### 1. Clone repositori

```bash
git clone https://github.com/username/amanah-digital.git
cd amanah-digital
```

### 2. Import database

Buka phpMyAdmin atau jalankan via terminal:

```bash
mysql -u root -p
```

```sql
CREATE DATABASE amanah_digital CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE amanah_digital;
SOURCE schema.sql;
```

### 3. Konfigurasi `.env`

Salin file contoh lalu sesuaikan isinya:

```bash
cp .env-example .env
```

Edit `.env`:

```dotenv
ADMIN_USERNAME=admin
ADMIN_PASSWORD_HASH=<hash bcrypt — lihat langkah 4>

DB_HOST=localhost
DB_NAME=amanah_digital
DB_USER=root
DB_PASS=
```

### 4. Generate password hash

```bash
php -r "echo password_hash('PASSWORD_ANDA', PASSWORD_BCRYPT) . PHP_EOL;"
```

Tempel hasilnya ke `ADMIN_PASSWORD_HASH` di `.env`.

### 5. Konfigurasi database di `config/db.php`

Pastikan kredensial database sesuai, atau gunakan variabel dari `.env`.

### 6. Jalankan aplikasi

Tempatkan folder di root web server (contoh LAMPP):

```
/opt/lampp/htdocs/amanah-digital/
```

Akses di browser:

| Halaman | URL |
|---|---|
| Landing page | `http://localhost/amanah-digital/` |
| Login admin | `http://localhost/amanah-digital/admin/login.php` |

---

## Petunjuk Penggunaan

### Untuk Orang Tua / Pengirim Masukan

1. Buka halaman utama aplikasi
2. Pilih **Topik** yang sesuai dari dropdown (contoh: Fasilitas, Pembelajaran, dll.)
3. Pilih **Sub-Topik** (muncul otomatis setelah topik dipilih)
4. Tulis masukan / aduan / aspirasi di kotak teks
5. Klik **Kirim** — masukan langsung tersimpan secara anonim
6. Satu perangkat dibatasi satu kali pengiriman per sesi (anti-spam)

### Untuk Admin

**Login**
1. Buka `admin/login.php`
2. Masukkan username dan password
3. Klik **Masuk**

**Melihat Data**
1. Gunakan **filter tanggal** di bagian atas untuk memilih periode
2. Klik **Tampilkan** — statistik, grafik, dan tabel akan diperbarui

**Kelola Topik**
- Klik nama topik untuk membuka daftar sub-topiknya
- Tombol **Hapus** untuk menghapus topik atau sub-topik
- Isi kolom di bawah daftar → klik **+ Tambah** untuk menambah sub-topik baru
- Isi kolom paling bawah → klik **+ Tambah Topik** untuk topik baru

**Export PDF**
1. Atur filter periode sesuai kebutuhan
2. Scroll ke paling bawah halaman
3. Klik tombol **⬇ Export PDF**
4. Browser akan membuka tab baru dengan tampilan laporan
5. Dialog cetak muncul otomatis → pilih **Save as PDF**

---

## Keamanan

- File `.env` **tidak di-commit** (tercantum di `.gitignore`)
- Password admin disimpan sebagai **bcrypt hash** — tidak pernah plain text
- Semua input divalidasi dan di-escape sebelum masuk ke database (**prepared statements**)
- Session di-regenerate setelah login untuk mencegah session fixation
- Rate limiting masukan via cookie untuk mencegah spam

---

## Lisensi

Proyek ini dikembangkan khusus untuk **Yayasan Ihsanul Amal Alabio**. Seluruh hak cipta milik yayasan.
