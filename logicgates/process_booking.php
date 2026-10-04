<?php
// Handler request booking (tanpa login). Menerima POST dari form booking, balas JSON (objek Respon).
// Nama file ini asumsi: sesuaikan/pindah kalau kelompok punya nama lain.

require_once __DIR__ . '/../callinglibs.php'; // autoloader: DBconnection, Respon, BillingCalculator

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Jakarta');

const DURASI_MIN       = 1;
const DURASI_MAX       = 12;
const MAX_UPLOAD_BYTES = 2 * 1024 * 1024; // 2 MB

function kirim(bool $status, string $message, array $data = [], int $http = 200): never
{
    http_response_code($http);
    echo json_encode(new Respon($status, $message, $data));
    exit;
}

// ambil input sebagai string rapi (aman kalau yang dikirim ternyata array)
function input(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : '';
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        kirim(false, 'Metode tidak diizinkan.', [], 405);
    }

    // ===== 1. AMBIL & VALIDASI INPUT =====
    $nama_depan    = input('nama_depan');
    $nama_belakang = input('nama_belakang');
    $no_wa         = preg_replace('/[\s\-]/', '', input('no_wa'));
    $tanggal       = input('pesan_untuk_tanggal') ?: input('pesan_tanggal');
    $jam_mulai     = input('jam_mulai');
    $ruang_id      = filter_var(input('ruang_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $durasi_jam    = filter_var(input('durasi', '1'), FILTER_VALIDATE_INT,
                                ['options' => ['min_range' => DURASI_MIN, 'max_range' => DURASI_MAX]]);

    if ($nama_depan === '' || mb_strlen($nama_depan) > 255 || mb_strlen($nama_belakang) > 255) {
        kirim(false, 'Nama depan wajib diisi (maksimal 255 karakter).', [], 422);
    }
    if (!preg_match('/^\+?[0-9]{9,13}$/', $no_wa)) { // kolom no_wa hanya 14 karakter
        kirim(false, 'Nomor WhatsApp tidak valid.', [], 422);
    }
    if ($ruang_id === false) {
        kirim(false, 'Ruangan tidak valid.', [], 422);
    }
    if ($durasi_jam === false) {
        kirim(false, 'Durasi harus angka ' . DURASI_MIN . ' sampai ' . DURASI_MAX . ' jam.', [], 422);
    }

    $tgl = DateTime::createFromFormat('!Y-m-d', $tanggal);
    if (!$tgl || $tgl->format('Y-m-d') !== $tanggal) {
        kirim(false, 'Format tanggal tidak valid.', [], 422);
    }
    if ($tgl < new DateTime('today')) {
        kirim(false, 'Tanggal booking tidak boleh di masa lalu.', [], 422);
    }

    $jam = DateTime::createFromFormat('!H:i', $jam_mulai);
    if (!$jam || $jam->format('H:i') !== $jam_mulai) {
        kirim(false, 'Format jam mulai tidak valid (contoh 14:00).', [], 422);
    }
    $mulai_menit   = (int)$jam->format('H') * 60 + (int)$jam->format('i');
    $selesai_menit = $mulai_menit + $durasi_jam * 60;
    if ($selesai_menit > 24 * 60) {
        kirim(false, 'Booking harus selesai sebelum tengah malam.', [], 422);
    }

    // ===== 2. VALIDASI FILE BUKTI PEMBAYARAN (belum dipindah) =====
    $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $file    = $_FILES['bukti_pembayaran'] ?? null;

    if (!is_array($file) || !is_string($file['name'] ?? null) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        kirim(false, 'Bukti pembayaran wajib diunggah.', [], 422);
    }
    if (!is_uploaded_file($file['tmp_name']) || $file['size'] > MAX_UPLOAD_BYTES) {
        kirim(false, 'Bukti pembayaran harus berupa gambar maksimal 2 MB.', [], 422);
    }
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$ext]) || $allowed[$ext] !== $mime) {
        kirim(false, 'File harus gambar JPG, PNG, atau WEBP.', [], 422);
    }

    // ===== 3. CEK KE DATABASE =====
    $db = new DBconnection();

    // 3a. ruangan harus ada dan aktif; ambil tarifnya
    $ruang = $db->send_query('SELECT id, tarif_per_jam FROM ruang WHERE id = $1 AND is_active = TRUE', [$ruang_id]);
    if (!$ruang->status) {
        error_log('process_booking cek ruang: ' . $ruang->message);
        kirim(false, 'Terjadi kesalahan server. Coba lagi nanti.', [], 500);
    }
    if (empty($ruang->data)) {
        kirim(false, 'Ruangan tidak ditemukan atau sedang tidak aktif.', [], 404);
    }
    $tarif = (float)($ruang->data[0]['tarif_per_jam'] ?? 0);

    // 3b. jadwal tidak boleh bentrok dengan booking yang SUDAH disetujui admin
    $bentrok = $db->send_query(
        'SELECT 1 FROM request_booking
          WHERE ruang_id = $1 AND pesan_untuk_tanggal = $2 AND approval_status = \'approved\'
            AND EXTRACT(EPOCH FROM jam_mulai) / 60 < $3
            AND (EXTRACT(EPOCH FROM jam_mulai) + EXTRACT(EPOCH FROM durasi)) / 60 > $4
          LIMIT 1',
        [$ruang_id, $tanggal, $selesai_menit, $mulai_menit]
    );
    if (!$bentrok->status) {
        error_log('process_booking cek bentrok: ' . $bentrok->message);
        kirim(false, 'Terjadi kesalahan server. Coba lagi nanti.', [], 500);
    }
    if (!empty($bentrok->data)) {
        kirim(false, 'Ruangan sudah terisi di jam tersebut. Silakan pilih jam atau ruangan lain.', [], 409);
    }

    // ===== 4. HITUNG BIAYA =====
    $billing = (new BillingCalculator())->calculateFinalBill($tarif, $durasi_jam);

    // ===== 5. SIMPAN FILE, LALU INSERT =====
    $dir = __DIR__ . '/../uploads/bukti_tf/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        error_log('process_booking: gagal membuat folder upload');
        kirim(false, 'Terjadi kesalahan server. Coba lagi nanti.', [], 500);
    }
    $filename = 'TF_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $target   = $dir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        error_log('process_booking: move_uploaded_file gagal');
        kirim(false, 'Gagal menyimpan bukti pembayaran. Coba lagi.', [], 500);
    }

    $insert = $db->send_query(
        'INSERT INTO request_booking
            (nama_depan, nama_belakang, no_wa, ruang_id, pesan_untuk_tanggal, jam_mulai, durasi, approval_status, bukti_pembayaran)
         VALUES ($1, $2, $3, $4, $5, $6, $7, \'pending\', $8)
         RETURNING id',
        [$nama_depan, $nama_belakang, $no_wa, $ruang_id, $tanggal, $jam_mulai, $durasi_jam . ' hours', 'uploads/bukti_tf/' . $filename]
    );
    if (!$insert->status) {
        @unlink($target); // jangan tinggalkan file yatim kalau insert gagal
        error_log('process_booking insert: ' . $insert->message);
        kirim(false, 'Gagal menyimpan data booking. Coba lagi nanti.', [], 500);
    }

    kirim(true, 'Request booking berhasil dibuat. Admin akan mengonfirmasi lewat WhatsApp.', [
        'id'          => $insert->data[0]['id'] ?? null,
        'total_biaya' => $billing['total'],
    ]);

} catch (Throwable $e) {
    error_log('process_booking error: ' . $e->getMessage());
    kirim(false, 'Terjadi kesalahan server. Coba lagi nanti.', [], 500);
}