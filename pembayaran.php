<?php
// HALAMAN 2: ringkasan + total + QRIS + upload bukti. Baru di sini "Kirim" menyimpan booking.
require_once __DIR__ . '/callinglibs.php'; // autoloader: DBconnection, BookingRequest, ...

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { // dibuka langsung lewat URL
    header('Location: bookingpage.php');
    exit();
}

$error = '';
$d = $info = [];
try {
    $v = BookingRequest::validate($_POST);
    if (!$v->status) {
        $error = $v->message;
    } else {
        $d = $v->data;
        $c = BookingRequest::check(new DBconnection(), $d);
        if (!$c->status) { $error = $c->message; } else { $info = $c->data; }
    }
} catch (Throwable $e) {
    error_log('pembayaran: ' . $e->getMessage());
    $error = 'Terjadi kesalahan server. Coba lagi nanti.';
}
$h      = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$rupiah = fn($n) => 'Rp ' . number_format((float)$n, 0, ',', '.');
$punyaQris = is_file(__DIR__ . '/assets/qris.png'); // gambar QRIS statis taruh di assets/qris.png
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pembayaran</title>
    <style>
        body{font-family:system-ui,sans-serif;max-width:480px;margin:0 auto;padding:16px}
        table{width:100%;border-collapse:collapse} td{padding:6px 0;vertical-align:top} td:first-child{color:#555;width:38%}
        .total{font-size:1.6rem;font-weight:700;margin:8px 0}
        .qris{max-width:100%;display:block;margin:12px auto;border:1px dashed #999;padding:8px;box-sizing:border-box;text-align:center}
        input,button,a.btn{width:100%;padding:10px;margin-top:8px;box-sizing:border-box;font-size:1rem}
        .err{color:#c00} .ok{color:#080}
    </style>
</head>
<body>
<?php if ($error !== ''): ?>
    <h1>Booking belum bisa dilanjutkan</h1>
    <p class="err"><?= $h($error) ?></p>
    <p><a href="bookingpage.php">&larr; Kembali ke form booking</a></p>
<?php else: ?>
    <h1>Pembayaran</h1>
    <table>
        <tr><td>Nama</td><td><?= $h($d['nama_depan'] . ' ' . $d['nama_belakang']) ?></td></tr>
        <tr><td>No WhatsApp</td><td><?= $h($d['no_wa']) ?></td></tr>
        <tr><td>Ruangan</td><td><?= $h($info['ruang_nama']) ?> (<?= $h($info['kategori']) ?>)</td></tr>
        <tr><td>Tanggal</td><td><?= $h($d['tanggal']) ?></td></tr>
        <tr><td>Jam</td><td><?= $h($d['jam_mulai']) ?> &ndash; <?= $h($d['jam_selesai']) ?> (<?= (int)$d['durasi_jam'] ?> jam)</td></tr>
        <tr><td>Tarif</td><td><?= $rupiah($info['tarif']) ?>/jam</td></tr>
    </table>

    <div id="bayar">
        <p>Total yang harus dibayar:</p>
        <div class="total"><?= $rupiah($info['total']) ?></div>

        <div class="qris">
            <?php if ($punyaQris): ?>
                <img src="assets/qris.png" alt="QRIS" style="max-width:100%">
            <?php else: ?>
                [Gambar QRIS belum dipasang. Simpan file QRIS di <code>assets/qris.png</code>]
            <?php endif; ?>
        </div>
        <p>Scan QRIS, lalu <strong>masukkan nominal tepat <?= $rupiah($info['total']) ?></strong>
           (QRIS statis tidak mengisi nominal otomatis). Setelah itu unggah bukti pembayaran di bawah.</p>

        <form id="form-bukti" action="logicgates/process_booking.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="nama_depan" value="<?= $h($d['nama_depan']) ?>">
            <input type="hidden" name="nama_belakang" value="<?= $h($d['nama_belakang']) ?>">
            <input type="hidden" name="no_wa" value="<?= $h($d['no_wa']) ?>">
            <input type="hidden" name="ruang_id" value="<?= (int)$d['ruang_id'] ?>">
            <input type="hidden" name="pesan_untuk_tanggal" value="<?= $h($d['tanggal']) ?>">
            <input type="hidden" name="jam_mulai" value="<?= $h($d['jam_mulai']) ?>">
            <input type="hidden" name="durasi" value="<?= (int)$d['durasi_jam'] ?>">

            <label>Bukti pembayaran (JPG/PNG/WEBP, maks. 2 MB)
                <input type="file" name="bukti_pembayaran" accept="image/jpeg,image/png,image/webp" required>
            </label>
            <button type="submit" id="btn-kirim">Kirim Booking</button>
        </form>
        <p id="hasil"></p>
        <p><a href="bookingpage.php">&larr; Ubah data booking</a></p>
    </div>

    <script>
        const form = document.getElementById('form-bukti');
        const hasil = document.getElementById('hasil');
        const tombol = document.getElementById('btn-kirim');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            tombol.disabled = true;
            hasil.className = ''; hasil.textContent = 'Mengirim...';
            try {
                const resp = await fetch(form.action, { method: 'POST', body: new FormData(form) });
                const data = await resp.json();
                hasil.textContent = data.message;
                hasil.className = data.status ? 'ok' : 'err';
                if (data.status) {
                    document.getElementById('bayar').innerHTML =
                        '<h2 class="ok">Booking terkirim</h2><p>' + data.message.replace(/</g, '&lt;') +
                        '</p><p>Mohon tunggu konfirmasi dari admin via WhatsApp.</p><p><a href="dashboard.php">Kembali ke beranda</a></p>';
                } else {
                    tombol.disabled = false;
                }
            } catch (err) {
                hasil.className = 'err';
                hasil.textContent = 'Gagal menghubungi server. Coba lagi.';
                tombol.disabled = false;
            }
        });
    </script>
<?php endif; ?>
</body>
</html>