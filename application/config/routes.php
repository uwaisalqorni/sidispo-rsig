<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/userguide3/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'welcome';
$route['404_override']       = '';
$route['translate_uri_dashes'] = FALSE;

/*
| -------------------------------------------------------------------------
| SiDispo REST API Routes — v1
| Base URL: http://localhost/SiDispo/api/v1/
| Semua endpoint (kecuali auth/login) wajib header: Authorization: Bearer {token}
| -------------------------------------------------------------------------
*/

// ── AUTH ──────────────────────────────────────────────────────────────────
$route['api/v1/auth/login']['post']              = 'Auth/login';            // POST  – Login → JWT
$route['api/v1/auth/me']['get']                  = 'Auth/me';              // GET   – Data user login
$route['api/v1/auth/profile']['post']            = 'Auth/update_profile';  // POST  – Update profil + foto (multipart)
$route['api/v1/auth/change-password']['put']     = 'Auth/change_password'; // PUT   – Ganti password


// ── DASHBOARD ─────────────────────────────────────────────────────────────
$route['api/v1/dashboard']['get']           = 'Dashboard/index';  // GET   – Stats + activity + unit progress

// ── DISPOSISI ─────────────────────────────────────────────────────────────
$route['api/v1/disposisi']['get']                          = 'Disposisi/index';        // GET    – List (role-aware)
$route['api/v1/disposisi']['post']                         = 'Disposisi/create';       // POST   – Buat disposisi + assign penerima
$route['api/v1/disposisi/(:num)']['get']                   = 'Disposisi/detail/$1';    // GET    – Detail + penerima + files
$route['api/v1/disposisi/(:num)']['put']                   = 'Disposisi/update/$1';    // PUT    – Edit (sebelum ada progress)
$route['api/v1/disposisi/(:num)']['delete']                = 'Disposisi/destroy/$1';   // DELETE – Arsipkan disposisi
$route['api/v1/disposisi/stats']['get']                    = 'Disposisi/stats';        // GET    – Statistik global
$route['api/v1/disposisi/overdue']['get']                  = 'Disposisi/overdue';      // GET    – Daftar overdue

// ── PENERIMA ──────────────────────────────────────────────────────────────
$route['api/v1/penerima/(:num)']['get']                    = 'Penerima/index/$1';          // GET – List penerima + status per disposisi
$route['api/v1/penerima/(:num)/status']['put']             = 'Penerima/update_status/$1';  // PUT – Update status (STAF only)

// ── PROGRESS (append-only log) ────────────────────────────────────────────
$route['api/v1/progress/(:num)']['get']                    = 'Progress/index/$1';           // GET  – Timeline log per penerima
$route['api/v1/progress/(:num)']['post']                   = 'Progress/store/$1';           // POST – Tambah log progress + catatan
$route['api/v1/progress/disposisi/(:num)']['get']          = 'Progress/by_disposisi/$1';    // GET  – Timeline gabungan per disposisi

// ── FOLDER ────────────────────────────────────────────────────────────────
$route['api/v1/folder']['get']                             = 'Folder/index';            // GET    – List folder (nested tree)
$route['api/v1/folder']['post']                            = 'Folder/create';           // POST   – Buat folder/subfolder
$route['api/v1/folder/(:num)']['put']                      = 'Folder/update/$1';        // PUT    – Edit nama/warna
$route['api/v1/folder/(:num)']['delete']                   = 'Folder/delete/$1';        // DELETE – Hapus (jika kosong)

// ── SURAT MASUK ───────────────────────────────────────────────────────────
$route['api/v1/surat']['get']                              = 'Surat/index';             // GET    – List surat masuk
$route['api/v1/surat']['post']                             = 'Surat/create';            // POST   – Input surat + upload file
$route['api/v1/surat/(:num)']['get']                       = 'Surat/detail/$1';         // GET    – Detail surat + lampiran
$route['api/v1/surat/(:num)']['post']                      = 'Surat/update/$1';         // POST   – Edit surat masuk + file baru
$route['api/v1/surat/(:num)']['delete']                    = 'Surat/destroy/$1';        // DELETE – Hapus surat masuk
$route['api/v1/surat/file/(:num)']['delete']               = 'Surat/delete_file/$1';    // DELETE – Hapus 1 lampiran file surat

// ── MASTER PERIHAL ────────────────────────────────────────────────────────
$route['api/v1/perihal']['get']                            = 'Perihal/index';                // GET    – List perihal (semua role)
$route['api/v1/perihal/(:num)']['get']                     = 'Perihal/show/$1';              // GET    – Detail perihal
$route['api/v1/admin/perihal']['post']                     = 'Perihal/create';               // POST   – Buat perihal (ADMIN)
$route['api/v1/admin/perihal/(:num)']['put']               = 'Perihal/update/$1';            // PUT    – Update perihal (ADMIN)
$route['api/v1/admin/perihal/(:num)']['delete']            = 'Perihal/destroy/$1';           // DELETE – Hapus perihal (ADMIN)
$route['api/v1/admin/perihal/(:num)/toggle']['put']        = 'Perihal/toggle/$1';            // PUT    – Toggle aktif/nonaktif (ADMIN)

// ── NOTIFIKASI ────────────────────────────────────────────────────────────
$route['api/v1/notifikasi']['get']                         = 'Notifikasi/index';        // GET    – List notifikasi
$route['api/v1/notifikasi/(:num)/baca']['put']             = 'Notifikasi/read/$1';      // PUT    – Tandai 1 notifikasi dibaca
$route['api/v1/notifikasi/baca-semua']['put']              = 'Notifikasi/read_all';     // PUT    – Tandai semua dibaca

// ── CRON ──────────────────────────────────────────────────────────────────
$route['api/v1/cron/check-overdue']['get']                 = 'Cron/check_overdue';      // GET    – Auto-flag overdue (CLI/token)

// ── ADMIN — Users ─────────────────────────────────────────────────────────
$route['api/v1/admin/stats']['get']                        = 'Admin/system_stats';          // GET    – Statistik sistem
$route['api/v1/admin/users']['get']                        = 'Admin/users_index';           // GET    – List semua pengguna
$route['api/v1/admin/users']['post']                       = 'Admin/users_create';          // POST   – Buat pengguna baru
$route['api/v1/admin/users/(:num)']['get']                 = 'Admin/users_show/$1';         // GET    – Detail pengguna
$route['api/v1/admin/users/(:num)']['put']                 = 'Admin/users_update/$1';       // PUT    – Update data pengguna
$route['api/v1/admin/users/(:num)']['delete']              = 'Admin/users_delete/$1';       // DELETE – Nonaktifkan pengguna
$route['api/v1/admin/users/(:num)/reset-password']['post'] = 'Admin/users_reset_password/$1'; // POST – Reset password

// ── ADMIN — Folders ────────────────────────────────────────────────────────
$route['api/v1/admin/folders']['get']                      = 'Admin/folders_index';         // GET    – List semua folder
$route['api/v1/admin/folders']['post']                     = 'Admin/folders_create';        // POST   – Buat folder baru
$route['api/v1/admin/folders/(:num)']['put']               = 'Admin/folders_update/$1';     // PUT    – Update folder
$route['api/v1/admin/folders/(:num)']['delete']            = 'Admin/folders_delete/$1';     // DELETE – Hapus folder

// ── ADMIN — Settings ──────────────────────────────────────────────────────
$route['api/v1/admin/settings']['get']                     = 'Admin/settings_index';        // GET    – Semua konfigurasi
$route['api/v1/admin/settings']['put']                     = 'Admin/settings_update';       // PUT    – Update konfigurasi (bulk)
$route['api/v1/admin/settings/test-email']['post']          = 'Admin/settings_test_email';   // POST   – Uji coba kirim email SMTP

// ── USERS (general – accessible by all authenticated users) ──────────────
$route['api/v1/users']['get']                              = 'Admin/users_active';          // GET    – Daftar user aktif (untuk pilih penerima disposisi)

// ── RTL (Rencana Tindak Lanjut) ──────────────────────────────────────────
$route['api/v1/rtl']['get']                                = 'Rtl/index';
$route['api/v1/rtl']['post']                               = 'Rtl/create';
$route['api/v1/rtl/disposisi-selesai']['get']              = 'Rtl/disposisi_selesai';
$route['api/v1/rtl/(:num)']['get']                         = 'Rtl/detail/$1';
$route['api/v1/dashboard-rtl']['get']                      = 'DashboardRtl/index';
$route['api/v1/rtl/progress/(:num)']['put']                = 'Rtl/progress/$1';
