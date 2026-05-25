# Panduan Implementasi & Deploy SiDispo di XAMPP (Local Network / LAN)

Panduan ini ditujukan agar aplikasi SiDispo dapat diakses oleh komputer *Client* lain yang berada dalam satu jaringan (Wi-Fi / LAN) dengan Server/PC Utama yang menjalankan XAMPP.

---

## 1. Persiapan IP Address Server
Komputer yang diinstal XAMPP (Server) harus menggunakan **Static IP Address** agar alamatnya tidak berubah-ubah setiap kali restart.
Contoh dalam panduan ini menggunakan IP: `192.168.0.194`.

## 2. Konfigurasi Backend (CodeIgniter 3)

Buka folder proyek `c:\xampp\htdocs\SiDispo\`.

1. **Ubah Base URL**
   Buka file `application/config/config.php` dan sesuaikan IP:
   ```php
   $config['base_url'] = 'http://192.168.0.194/SiDispo/';
   ```

2. **Ubah Koneksi Database (Jika Diperlukan)**
   Buka file `application/config/database.php`. Jika database ada di komputer yang sama (XAMPP yang sama), `hostname` tetap `localhost` sudah cukup. Tapi jika ingin di-set eksplisit juga silakan:
   ```php
   $db['default']['hostname'] = 'localhost'; // Atau '192.168.0.194'
   $db['default']['username'] = 'root';
   $db['default']['password'] = 'bismillah';
   ```

---

## 3. Build Frontend (Vue 3) untuk Produksi

Buka folder frontend `c:\xampp\htdocs\SiDispo\sidispo-frontend\`.

1. **Ubah URL Endpoint API**
   Buka file `src/api/axios.js` (atau `.env.production` jika ada), pastikan mengarah ke IP Server:
   ```javascript
   baseURL: 'http://192.168.0.194/SiDispo/api/v1'
   ```

2. **Ubah Router Base URL (Opsional tapi disarankan)**
   Jika frontend tidak di-host di root domain, buka `vite.config.js` dan tambahkan `base`:
   ```javascript
   export default defineConfig({
     base: '/SiDispo/app/', // Sesuaikan dengan folder tempat dist nanti diletakkan
     // ...
   })
   ```

3. **Jalankan Proses Build**
   Hentikan proses `npm run dev` jika sedang berjalan. Lalu jalankan perintah build:
   ```bash
   npm run build
   ```
   *Perintah ini akan membuat folder baru bernama `dist` di dalam `sidispo-frontend`.*

4. **Kopi Hasil Build ke Root XAMPP**
   Buat folder baru di dalam `htdocs/SiDispo` (misal: `app`).
   Copy seluruh isi dari folder `sidispo-frontend/dist/` ke dalam `htdocs/SiDispo/app/`.

---

## 4. Konfigurasi XAMPP dan Windows Firewall

Agar komputer Client bisa mengakses Apache di komputer Server:

1. **Jalankan Apache & MySQL**
   Buka XAMPP Control Panel, klik **Start** pada Apache dan MySQL.

2. **Buka Port 80 di Windows Firewall**
   - Buka `Windows Defender Firewall with Advanced Security`.
   - Klik **Inbound Rules** -> **New Rule...**
   - Pilih **Port**, lalu klik Next.
   - Ketik `80` di *Specific local ports*, lalu Next.
   - Pilih **Allow the connection**, Next hingga selesai, dan beri nama "XAMPP Apache Port 80".

---

## 5. Cara Akses oleh Client

Sekarang pergi ke komputer klien mana pun yang tersambung ke WiFi/LAN yang sama. Buka Google Chrome / Edge, lalu ketik alamat berikut:

- **Akses Aplikasi (Frontend)**:
  `http://192.168.0.194/SiDispo/app/`
  *(Sesuaikan nama folder `app` dengan folder tempat Anda meletakkan hasil build Vue)*

- **Akses Backend / API (Jika ingin mengetes)**:
  `http://192.168.0.194/SiDispo/`

---

### Tips Troubleshooting
1. **Frontend Blank / Whitescreen**: Hal ini sering terjadi jika *base path* pada build Vue tidak sesuai dengan lokasi penempatan di htdocs. Pastikan konfigurasi `base: '/SiDispo/app/',` di `vite.config.js` sudah diatur sebelum di-build.
2. **CORS Error**: Berarti ada Endpoint di Axios yang masih menunjuk ke `localhost` ketimbang ke IP Address `192.168.0.194`.
3. **Site Cannot Be Reached**: Bisa diakibatkan oleh Windows Firewall di komputer Server yang masih memblokir koneksi port 80, atau klien tersebut tidak berada pada jaringan LAN/WiFi yang sama.
