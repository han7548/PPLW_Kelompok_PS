<?php
// ===== 1. PERSIAPAN =====
session_start();                                // nyalakan session (ingatan server)

require_once __DIR__ . '/../callinglibs.php';   // autoloader: memuat DBconnection, Respon, User

const LOGIN_PAGE     = '../login.php';          // tujuan kalau gagal
const DASHBOARD_PAGE = '../dashboard.php';      // tujuan kalau berhasil

// ===== 2. HANYA TERIMA POST =====
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {    // dibuka lewat URL langsung?
    header('Location: ' . LOGIN_PAGE);          // suruh browser pindah
    exit();                                     // berhenti, jangan lanjut
}

// ===== 3. AMBIL & CEK INPUT =====
$username = trim($_POST['username'] ?? '');     // trim: buang spasi di ujung
$password = $_POST['password'] ?? '';           // password tidak di-trim

if ($username === '' || $password === '') {     // salah satu kosong?
    $_SESSION['error'] = 'Username dan password wajib diisi!';
    header('Location: ' . LOGIN_PAGE);
    exit();
}

// ===== 4. COBA LOGIN KE DATABASE =====
try {
    $user   = new User(new DBconnection());     // sambung ke PostgreSQL
    $result = $user->login($username, $password); // hasilnya objek Respon
} catch (Throwable $e) {                        // DB mati / error lain
    error_log('process_login error: ' . $e->getMessage()); // detail ke log server
    $_SESSION['error'] = 'Terjadi kesalahan server. Coba lagi nanti.'; // pesan umum
    header('Location: ' . LOGIN_PAGE);
    exit();
}

// ===== 5. LOGIN GAGAL? (username/password salah) =====
if (!$result->status) {
    $_SESSION['error'] = $result->message;      // pesan dari class User
    header('Location: ' . LOGIN_PAGE);
    exit();
}

// ===== 6. LOGIN BERHASIL: BUAT SESSION =====
session_regenerate_id(true);                    // ganti ID session (cegah session fixation)

$_SESSION['user_id']      = $result->data['id'];
$_SESSION['username']     = $result->data['username'];
$_SESSION['nama_lengkap'] = $result->data['nama_lengkap'] ?? $result->data['username'];
$_SESSION['role']         = $result->data['role'] ?? 'admin';
$_SESSION['is_logged_in'] = true;               // penanda "sudah login"

// ===== 7. KE DASHBOARD =====
header('Location: ' . DASHBOARD_PAGE);
exit();