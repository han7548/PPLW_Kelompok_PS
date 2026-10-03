<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../callinglibs.php';

$nama_depan    = $_POST['nama_depan'] ?? '';
$nama_belakang = $_POST['nama_belakang'] ?? '';
$no_wa         = $_POST['no_wa'] ?? '';
$ruang_id      = $_POST['ruang_id'] ?? null;
$pesan_tanggal = $_POST['pesan_untuk_tanggal'] ?? $_POST['pesan_tanggal'] ?? '';
$jam_mulai     = $_POST['jam_mulai'] ?? '';
$durasi_jam    = $_POST['durasi'] ?? 1;

if (empty($nama_depan) || empty($no_wa) || empty($ruang_id) || empty($pesan_tanggal) || empty($jam_mulai)) {
    echo json_encode(new Respon(false, "Mohon lengkapi semua data formulir booking!"));
    exit;
}

$durasi_interval = $durasi_jam . ' hours';

$bukti_filepath = null;
if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = "../uploads/bukti_tf/";
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_extension = pathinfo($_FILES["bukti_pembayaran"]["name"], PATHINFO_EXTENSION);
    $filename = "TF_" . time() . "_" . uniqid() . "." . $file_extension;
    
    $target_file = $upload_dir . $filename;
    if (move_uploaded_file($_FILES["bukti_pembayaran"]["tmp_name"], $target_file)) {
        $bukti_filepath = "uploads/bukti_tf/" . $filename;
    }
}

try {
    $db = new DBconnection();

    $query = "
        INSERT INTO request_booking 
        (nama_depan, nama_belakang, no_wa, ruang_id, pesan_untuk_tanggal, jam_mulai, durasi, approval_status, bukti_pembayaran) 
        VALUES ($1, $2, $3, $4, $5, $6, $7, 'pending', $8)
    ";

    $params = [
        $nama_depan, 
        $nama_belakang, 
        $no_wa, 
        $ruang_id, 
        $pesan_tanggal, 
        $jam_mulai, 
        $durasi_interval, 
        $bukti_filepath
    ];

    $response = $db->send_query($query, $params);
    echo json_encode($response);

} catch (dbexceptions $e) {
    echo json_encode(new Respon(false, "Gagal menyimpan data booking: " . $e->getMessage()));
}
?>