# SiWarung

SiWarung adalah sistem informasi manajemen inventaris berbasis web untuk warung/toko kelontong skala mikro. Sistem ini dikembangkan sebagai Karya Terapan (Tugas Akhir) oleh **I Kadek Indra Satya Ananda** (NIM 2301020078, Program Studi Informatika, Primakara University), dan telah diimplementasikan serta dipakai secara operasional di **Warung Bu Ratih**.

Fokus utama SiWarung adalah pencatatan stok berbasis batch dengan konsumsi **First In First Out (FIFO)**, sehingga harga pokok penjualan (HPP), laporan laba rugi, dan pemantauan barang mendekati kedaluwarsa dapat dihitung secara akurat per batch — bukan hanya berdasarkan rata-rata harga beli.

## Fitur utama

| Modul | Fitur |
|---|---|
| Autentikasi & profil | Masuk, keluar, dan pengelolaan profil (Laravel Breeze) |
| Dashboard | Ringkasan penjualan, stok rendah, peringatan kedaluwarsa, dan pergerakan stok 7 hari terakhir |
| Data barang | Tambah, ubah, hapus, pencarian, serta filter kategori dan stok rendah |
| Penerimaan barang | Stok masuk membentuk batch baru dengan harga beli dan tanggal kedaluwarsa masing-masing |
| Impor stok via CSV | Templat unduhan, validasi seluruh baris, simpan semua-atau-tidak-sama-sekali, dan pendaftaran otomatis barang baru |
| Markup harga otomatis | Menghitung harga jual dari harga beli berdasarkan satuan barang, dibulatkan ke kelipatan Rp500; tetap bisa diisi manual |
| Penyesuaian stok | Penyesuaian masuk/keluar manual beserta riwayat pergerakan stok |
| Transaksi penjualan | Status lunas/piutang, validasi stok, konsumsi FIFO otomatis, dan rincian batch yang terpakai |
| Pembatalan & nota | Pembatalan transaksi memulihkan stok ke batch asal; cetak nota |
| Kedaluwarsa | Penanda, halaman pemantauan khusus, dan perintah terjadwal harian |
| Penyusutan | Pencatatan penyusutan per batch dengan alasan, dan laporan penyusutan |
| Laporan | Laporan penjualan, laba rugi berbasis HPP per batch, dan laporan penyusutan |

## Teknologi

- PHP 8.3+ dengan Laravel 13
- SQLite (basis data satu berkas, sesuai untuk usaha mikro satu lokasi)
- Blade, Tailwind CSS, dan Alpine.js untuk antarmuka
- [Pest](https://pestphp.com/) untuk pengujian unit/feature (backend)
- [Playwright](https://playwright.dev/) untuk pengujian end-to-end (e2e/)

## Menjalankan secara lokal

### Cara cepat (macOS)

Klik dua kali **`Jalankan SiWarung.command`**, atau jalankan lewat terminal:

```bash
bash start.sh
```

Skrip ini otomatis memeriksa dependensi, menyiapkan `.env` dan `APP_KEY`, menjalankan migrasi, lalu menyalakan server di `http://127.0.0.1:8000` dan membuka browser. Opsi yang tersedia:

```
bash start.sh [opsi]

  --lan          bisa dibuka dari HP/laptop lain yang satu WiFi
  --tunnel       buat alamat publik sementara (Cloudflare Tunnel) untuk demo/sidang
  --port ANGKA   pakai port tertentu (bawaan 8000)
  --fresh        hapus semua data lalu isi ulang dengan data demo
  --no-open      jangan buka browser otomatis
  -h, --help     tampilkan bantuan
```

### Cara manual

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate

npm run build   # atau `npm run dev` saat mengembangkan tampilan
php artisan serve
```

Aplikasi dapat diakses di `http://127.0.0.1:8000`.

## Menjalankan pengujian

Pengujian backend (Pest):

```bash
php artisan test
```

Pengujian end-to-end (Playwright), dari dalam folder `e2e/`:

```bash
cd e2e
npm install
cp .env.example .env   # isi E2E_EMAIL/E2E_PASSWORD dan *_API_KEY sesuai akun & server lokal
npx playwright test
```

Kredensial akun untuk pengujian e2e **tidak** ditulis langsung di berkas `.spec.ts` — isi lewat `e2e/.env` (lihat `e2e/.env.example`) agar tidak ikut tersimpan di riwayat Git.

## Lisensi

Proyek ini dikembangkan untuk keperluan akademik (Karya Terapan/Tugas Akhir) di Primakara University.
