<?php
session_start();

require_once __DIR__ . '/../callinglibs.php'; // autoloader: DBconnection, Respon, User, dll

const LOGIN_PAGE     = '../login.php';     // SESUAIKAN: halaman form login
const DASHBOARD_PAGE = '../dashboard.php';

// Hanya terima request POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . LOGIN_PAGE);
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? ''; // jangan di-trim: spasi bisa bagian dari password

if ($username === '' || $password === '') {
    $_SESSION['error'] = 'Username dan password wajib diisi!';
    header('Location: ' . LOGIN_PAGE);
    exit();
}

try {
    $user   = new User(new DBconnection());
    $result = $user->login($username, $password);
} catch (Throwable $e) {
    // termasuk dbexceptions kalau koneksi gagal
    error_log('process_login error: ' . $e->getMessage());
    $_SESSION['error'] = 'Terjadi kesalahan server. Coba lagi nanti.';
    header('Location: ' . LOGIN_PAGE);
    exit();
}

if (!$result->status) {
    $_SESSION['error'] = $result->message;
    header('Location: ' . LOGIN_PAGE);
    exit();
}

session_regenerate_id(true); // cegah session fixation

$_SESSION['user_id']      = $result->data['id'];
$_SESSION['username']     = $result->data['username'];
$_SESSION['nama_lengkap'] = $result->data['nama_lengkap'] ?? $result->data['username'];
$_SESSION['role']         = $result->data['role'] ?? 'admin';
$_SESSION['is_logged_in'] = true;

header('Location: ' . DASHBOARD_PAGE);
exit();