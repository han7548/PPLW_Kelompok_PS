<?php
// FORM TES SEMENTARA (jangan di-commit). Daftar ruang diambil dari database.
require_once __DIR__ . '/callinglibs.php'; // autoloader: DBconnection, Respon

$opsi  = [];
$error = '';

try {
    $db  = new DBconnection();
    $res = $db->send_query(
        'SELECT r.id, r.nama, r.tarif_per_jam,
                kr.nama AS kategori, kr.deskripsi AS kapasitas,
                COALESCE(string_agg(u.nama, \', \' ORDER BY u.nama), \'-\') AS perangkat
           FROM ruang r
           JOIN kategori_ruang kr ON kr.id = r.kategori_ruang
           LEFT JOIN kategori_ruang_unit kru ON kru.kategori_ruang_id = kr.id
           LEFT JOIN unit u ON u.id = kru.unit_id AND u.is_active = TRUE
          WHERE r.is_active = TRUE
          GROUP BY r.id, kr.id
          ORDER BY r.tarif_per_jam, r.nama'
    );
    if ($res->status) {
        $opsi = $res->data;
    } else {
        $error = 'Gagal mengambil daftar ruang.';
        error_log('test_booking: ' . $res->message);
    }
} catch (Throwable $e) {
    $error = 'Database tidak bisa dihubungi.';
    error_log('test_booking: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tes Booking (sementara, jangan di-commit)</title>
</head>
<body>
    <h1>Tes Request Booking</h1>

    <?php if ($error !== ''): ?>
        <p style="color:red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form action="logicgates/process_booking.php" method="POST" enctype="multipart/form-data">
        <p>Nama depan:<br>
            <input type="text" name="nama_depan" placeholder="Budi"></p>

        <p>Nama belakang:<br>
            <input type="text" name="nama_belakang" placeholder="Santoso"></p>

        <p>No WhatsApp:<br>
            <input type="text" name="no_wa" placeholder="08123456789"></p>

        <p>Pilih ruangan:<br>
            <select name="ruang_id">
                <?php foreach ($opsi as $r): ?>
                    <option value="<?= (int)$r['id'] ?>">
                        <?= htmlspecialchars($r['nama']) ?>
                        (<?= htmlspecialchars($r['kategori']) ?>)
                        | <?= htmlspecialchars($r['perangkat']) ?>
                        | <?= htmlspecialchars($r['kapasitas']) ?>
                        | Rp <?= number_format((float)$r['tarif_per_jam'], 0, ',', '.') ?>/jam
                    </option>
                <?php endforeach; ?>
            </select></p>

        <p>Tanggal:<br>
            <input type="date" name="pesan_untuk_tanggal"></p>

        <p>Jam mulai:<br>
            <input type="time" name="jam_mulai"></p>

        <p>Durasi (jam):<br>
            <input type="number" name="durasi" value="2" min="1" max="12"></p>

        <p>Bukti pembayaran (gambar):<br>
            <input type="file" name="bukti_pembayaran"></p>

        <button type="submit">Kirim</button>
    </form>
</body>
</html>