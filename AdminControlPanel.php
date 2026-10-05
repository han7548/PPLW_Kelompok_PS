<?php
require_once __DIR__ . '/callinglibs.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: AdminLogin.php");
    exit();
}

$db = new DBconnection();
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

$pesan_aksi = '';
if (isset($_SESSION['pesan_aksi'])) {
    $pesan_aksi = $_SESSION['pesan_aksi'];
    unset($_SESSION['pesan_aksi']);
}

// Helper function untuk handle upload foto
function handleUploadFoto() {
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileName = time() . '_' . basename($_FILES['foto']['name']);
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $fileName)) {
            return $fileName;
        }
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    $pesan_temp = "";
    $fotoName = handleUploadFoto();

    // --- BOOKING ---
    if ($action === 'approve_booking' || $action === 'reject_booking') {
        $booking_id = (int) $_POST['booking_id'];
        $status = ($action === 'approve_booking') ? 'approved' : 'rejected';
        $bookingClass = new Booking($db);
        $respon = $bookingClass->updateStatus($booking_id, $status, $admin_id);
        $pesan_temp = $respon->status ? "Booking #$booking_id di-$status!" : "Gagal update booking.";
    } elseif ($action === 'mark_arrived') {
        $booking_id = (int) $_POST['booking_id'];
        $bookingClass = new Booking($db);
        $respon = $bookingClass->updateStatus($booking_id, 'terkonfirmasi datang', $admin_id);
        $pesan_temp = $respon->status ? "Booking #$booking_id masuk ke Log Book!" : "Gagal update status.";
    }

    // --- KATEGORI RUANG ---
    elseif ($action === 'add_kategori_ruang') {
        $kr = new KategoriRuang($db);
        $respon = $kr->create($_POST['nama'], $_POST['deskripsi'], $admin_id);
        $pesan_temp = $respon->status ? "Kategori Ruang ditambahkan!" : "Gagal menambah kategori.";
    } elseif ($action === 'edit_kategori_ruang') {
        $query = "UPDATE kategori_ruang SET nama=$1, deskripsi=$2, updated_by=$3 WHERE id=$4";
        $respon = $db->send_query($query, [$_POST['nama'], $_POST['deskripsi'], $admin_id, $edit_id]);
        $pesan_temp = $respon->status ? "Kategori Ruang diupdate!" : "Gagal update.";
    } elseif ($action === 'delete_kategori_ruang') {
        $cat_id = (int)$_POST['delete_id'];
        // GATEKEEPER: Cek apakah masih ada ruangan aktif yang pakai kategori ini
        $cek = $db->send_query("SELECT COUNT(*) as total FROM ruang WHERE kategori_ruang = $1 AND is_active = true", [$cat_id]);
        if ($cek->data[0]['total'] > 0) {
            $pesan_temp = "Gagal: Hapus atau nonaktifkan semua ruangan di kategori ini terlebih dahulu!";
        } else {
            $respon = $db->send_query("UPDATE kategori_ruang SET is_active = false WHERE id=$1", [$cat_id]);
            $pesan_temp = $respon->status ? "Kategori Ruang disembunyikan!" : "Gagal hapus.";
        }
    }

    // --- RUANGAN ---
    elseif ($action === 'add_ruang') {
        $query = "INSERT INTO ruang (nama, jumlah_unit, kategori_ruang, tarif_per_jam, deskripsi, foto, created_by) VALUES ($1, $2, $3, $4, $5, $6, $7)";
        $respon = $db->send_query($query, [$_POST['nama'], (int)$_POST['jumlah_unit'], (int)$_POST['kategori_id'], (float)$_POST['tarif'], $_POST['deskripsi'], $fotoName, $admin_id]);
        $pesan_temp = $respon->status ? "Ruangan ditambahkan!" : "Gagal menambah ruangan.";
    } elseif ($action === 'edit_ruang') {
        if ($fotoName) {
            $query = "UPDATE ruang SET nama=$1, jumlah_unit=$2, kategori_ruang=$3, tarif_per_jam=$4, deskripsi=$5, foto=$6, updated_by=$7 WHERE id=$8";
            $respon = $db->send_query($query, [$_POST['nama'], (int)$_POST['jumlah_unit'], (int)$_POST['kategori_id'], (float)$_POST['tarif'], $_POST['deskripsi'], $fotoName, $admin_id, $edit_id]);
        } else {
            $query = "UPDATE ruang SET nama=$1, jumlah_unit=$2, kategori_ruang=$3, tarif_per_jam=$4, deskripsi=$5, updated_by=$6 WHERE id=$7";
            $respon = $db->send_query($query, [$_POST['nama'], (int)$_POST['jumlah_unit'], (int)$_POST['kategori_id'], (float)$_POST['tarif'], $_POST['deskripsi'], $admin_id, $edit_id]);
        }
        $pesan_temp = $respon->status ? "Ruangan diupdate!" : "Gagal update ruangan.";
    } elseif ($action === 'delete_ruang') {
        $respon = $db->send_query("UPDATE ruang SET is_active = false WHERE id=$1", [(int)$_POST['delete_id']]);
        $pesan_temp = $respon->status ? "Ruangan disembunyikan (History aman)!" : "Gagal menonaktifkan ruangan.";
    }

    // --- KATEGORI UNIT ---
    elseif ($action === 'add_kategori_unit') {
        $ku = new KategoriUnit($db);
        $respon = $ku->create($_POST['nama'], $_POST['deskripsi'], $admin_id);
        $pesan_temp = $respon->status ? "Kategori Unit ditambahkan!" : "Gagal.";
    } elseif ($action === 'edit_kategori_unit') {
        $query = "UPDATE kategori_unit SET nama=$1, deskripsi=$2, updated_by=$3 WHERE id=$4";
        $respon = $db->send_query($query, [$_POST['nama'], $_POST['deskripsi'], $admin_id, $edit_id]);
        $pesan_temp = $respon->status ? "Kategori Unit diupdate!" : "Gagal.";
    } elseif ($action === 'delete_kategori_unit') {
        $cat_id = (int)$_POST['delete_id'];
        // GATEKEEPER: Cek apakah masih ada unit aktif yang pakai kategori ini
        $cek = $db->send_query("SELECT COUNT(*) as total FROM unit WHERE kategori_unit = $1 AND is_active = true", [$cat_id]);
        if ($cek->data[0]['total'] > 0) {
            $pesan_temp = "Gagal: Hapus atau nonaktifkan semua unit di kategori ini terlebih dahulu!";
        } else {
            $respon = $db->send_query("UPDATE kategori_unit SET is_active = false WHERE id=$1", [$cat_id]);
            $pesan_temp = $respon->status ? "Kategori Unit disembunyikan!" : "Gagal hapus.";
        }
    }

    // --- UNIT ---
    elseif ($action === 'add_unit') {
        $query = "INSERT INTO unit (nama, jumlah_unit, kategori_unit, deskripsi, foto, created_by) VALUES ($1, $2, $3, $4, $5, $6)";
        $respon = $db->send_query($query, [$_POST['nama'], (int)$_POST['jumlah_unit'], (int)$_POST['kategori_id'], $_POST['deskripsi'], $fotoName, $admin_id]);
        $pesan_temp = $respon->status ? "Unit ditambahkan!" : "Gagal.";
    } elseif ($action === 'edit_unit') {
        if ($fotoName) {
            $query = "UPDATE unit SET nama=$1, jumlah_unit=$2, kategori_unit=$3, deskripsi=$4, foto=$5, updated_by=$6 WHERE id=$7";
            $respon = $db->send_query($query, [$_POST['nama'], (int)$_POST['jumlah_unit'], (int)$_POST['kategori_id'], $_POST['deskripsi'], $fotoName, $admin_id, $edit_id]);
        } else {
            $query = "UPDATE unit SET nama=$1, jumlah_unit=$2, kategori_unit=$3, deskripsi=$4, updated_by=$5 WHERE id=$6";
            $respon = $db->send_query($query, [$_POST['nama'], (int)$_POST['jumlah_unit'], (int)$_POST['kategori_id'], $_POST['deskripsi'], $admin_id, $edit_id]);
        }
        $pesan_temp = $respon->status ? "Unit diupdate!" : "Gagal.";
    } elseif ($action === 'delete_unit') {
        $respon = $db->send_query("UPDATE unit SET is_active = false WHERE id=$1", [(int)$_POST['delete_id']]);
        $pesan_temp = $respon->status ? "Unit disembunyikan!" : "Gagal menonaktifkan unit.";
    }

    $_SESSION['pesan_aksi'] = $pesan_temp;
    header("Location: AdminControlPanel.php");
    exit();
}


// FETCH DATA & MAPPING KATEGORI
$queryPending = "SELECT rb.*, r.nama as nama_ruang, r.tarif_per_jam, kr.nama as nama_kategori FROM request_booking rb LEFT JOIN ruang r ON rb.ruang_id = r.id LEFT JOIN kategori_ruang kr ON r.kategori_ruang = kr.id WHERE rb.approval_status = 'pending' ORDER BY rb.created_at ASC";
$pendingBookings = $db->send_query($queryPending)->data ?? [];

$queryApproved = "SELECT rb.*, r.nama as nama_ruang, r.tarif_per_jam, kr.nama as nama_kategori FROM request_booking rb LEFT JOIN ruang r ON rb.ruang_id = r.id LEFT JOIN kategori_ruang kr ON r.kategori_ruang = kr.id WHERE rb.approval_status = 'approved' ORDER BY rb.created_at ASC";
$approvedBookings = $db->send_query($queryApproved)->data ?? [];

$queryLogbook = "SELECT rb.*, r.nama as nama_ruang, kr.nama as nama_kategori FROM request_booking rb LEFT JOIN ruang r ON rb.ruang_id = r.id LEFT JOIN kategori_ruang kr ON r.kategori_ruang = kr.id WHERE rb.approval_status = 'terkonfirmasi datang' ORDER BY rb.updated_at DESC";
$logBookings = $db->send_query($queryLogbook)->data ?? [];

// HANYA AMBIL YANG MASIH AKTIF (TERMASUK KATEGORI!)
$listKategori = $db->send_query("SELECT * FROM kategori_ruang WHERE is_active = true ORDER BY id DESC")->data ?? [];
$listKategoriUnit = $db->send_query("SELECT * FROM kategori_unit WHERE is_active = true ORDER BY id DESC")->data ?? [];

$listRuang = $db->send_query("SELECT r.*, kr.nama as nama_kategori FROM ruang r LEFT JOIN kategori_ruang kr ON r.kategori_ruang = kr.id WHERE r.is_active = true")->data ?? [];
$listUnit = $db->send_query("SELECT u.*, ku.nama as nama_kategori FROM unit u LEFT JOIN kategori_unit ku ON u.kategori_unit = ku.id WHERE u.is_active = true")->data ?? [];

// Mapping ID Kategori ke Nama Kategori agar tampil di tabel Ruang & Unit
$mapKategoriRuang = [];
foreach ($listKategori as $kat) {
    $mapKategoriRuang[$kat['id']] = $kat['nama'];
}

$mapKategoriUnit = [];
foreach ($listKategoriUnit as $ku) {
    $mapKategoriUnit[$ku['id']] = $ku['nama'];
}

$db->close_connection();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Control Panel</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background-color: #f4f6f8; 
            color: #212529; 
            -webkit-font-smoothing: antialiased;
        }
        
        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            background: #ffffff; 
            padding: 20px 24px; 
            border-radius: 8px; 
            border: 1px solid #e2e8f0; 
            margin-bottom: 20px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.04); 
        }
        .header h2 { 
            margin: 0; 
            font-size: 24px; 
            font-weight: 700; 
            color: #1a202c; 
        }

        .alert { 
            padding: 12px 16px; 
            background-color: #d1fae5; 
            color: #065f46; 
            border: 1px solid #a7f3d0; 
            border-radius: 6px; 
            margin-bottom: 20px; 
            font-size: 14px;
            font-weight: 500; 
        }

        .container { display: flex; flex-direction: column; gap: 20px; }
        .row-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 20px; }

        .card { 
            background: #ffffff; 
            padding: 24px; 
            border-radius: 8px; 
            border: 1px solid #e2e8f0; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.04); 
        }
        .card h3 { 
            margin-top: 0; 
            margin-bottom: 18px; 
            font-size: 18px; 
            font-weight: 700; 
            border-bottom: 1px solid #edf2f7; 
            padding-bottom: 12px; 
        }
        .card h4 { 
            margin-top: 20px; 
            margin-bottom: 12px; 
            color: #2d3748; 
            font-size: 15px; 
            font-weight: 600; 
        }

        .table-responsive { overflow-x: auto; width: 100%; }
        table { width: 100%; border-collapse: collapse; margin-top: 5px; font-size: 13px; }
        th, td { padding: 10px 12px; border: 1px solid #e2e8f0; text-align: left; vertical-align: middle; }
        th { 
            background-color: #f8fafc; 
            color: #4a5568; 
            font-weight: 700; 
            text-transform: uppercase; 
            font-size: 11px; 
            letter-spacing: 0.5px; 
        }
        tr:nth-child(even) { background-color: #fbfcfd; }
        tr:hover { background-color: #f1f5f9; }

        label { font-weight: 600; font-size: 13px; color: #4a5568; display: block; margin-top: 12px; }
        input[type="text"], input[type="number"], select, textarea, input[type="file"] { 
            width: 100%; 
            padding: 9px 12px; 
            margin-top: 5px; 
            margin-bottom: 5px; 
            background-color: #ffffff; 
            border: 1px solid #cbd5e0; 
            border-radius: 6px; 
            color: #2d3748; 
            font-size: 14px; 
            font-family: inherit; 
        }
        input:focus, select:focus, textarea:focus { 
            border-color: #3182ce; 
            outline: 0; 
            box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.15); 
        }
        textarea { resize: vertical; min-height: 70px; }

        button, .btn { 
            padding: 7px 14px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            color: white; 
            font-weight: 600; 
            font-size: 13px; 
            display: inline-block; 
            transition: all 0.15s ease-in-out; 
            font-family: inherit;
        }
        .btn-success { background-color: #38a169; } .btn-success:hover { background-color: #2f855a; }
        .btn-danger { background-color: #e53e3e; } .btn-danger:hover { background-color: #c53030; }
        .btn-warning { background-color: #dd6b20; color: #ffffff; } .btn-warning:hover { background-color: #c05621; }
        .btn-primary { 
            background-color: #3182ce; 
            width: 100%; 
            padding: 10px; 
            margin-top: 12px; 
            font-size: 14px; 
        } 
        .btn-primary:hover { background-color: #2b6cb0; }

        .badge-kat { 
            background-color: #ebf8ff; 
            color: #2b6cb0; 
            padding: 3px 8px; 
            border-radius: 4px; 
            font-size: 12px; 
            font-weight: 600; 
            display: inline-block; 
            border: 1px solid #bee3f8; 
        }
        .desc-text { color: #718096; font-size: 13px; max-width: 200px; word-wrap: break-word; line-height: 1.4; }
        img.thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 5px; border: 1px solid #cbd5e0; }
        hr { border: 0; border-top: 1px dashed #e2e8f0; margin: 22px 0; }
        .action-btns { display: flex; gap: 6px; align-items: center; }
    </style>
</head>
<body>

    <!-- HEADER -->
    <div class="header">
        <h2>Admin Control Panel</h2>
        <div>
            <span style="margin-right: 15px; color: #4a5568;">Halo, <strong style="color: #1a202c;"><?= htmlspecialchars($admin_name) ?></strong></span>
            <a href="logout.php" class="btn btn-danger" style="text-decoration: none;">Logout</a>
        </div>
    </div>

    <?php if ($pesan_aksi): ?>
        <div class="alert"><?= htmlspecialchars($pesan_aksi) ?></div>
    <?php endif; ?>

    <div class="container">
        
        <!-- SECTION 1: PENDING BOOKINGS -->
        <div class="card">
            <h3 style="color: #dd6b20;">Pending Booking Requests (Review & Approval)</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>ID</th><th>PEMESAN & WA</th><th>DETAIL RUANGAN DIPESAN</th><th>TANGGAL & JAM</th><th>BUKTI DEPOSIT</th><th>AKSI</th></tr>
                    </thead>
                    <tbody>
                        <?php if (count($pendingBookings) > 0): ?>
                            <?php foreach ($pendingBookings as $b): ?>
                            <tr>
                                <td><strong style="color:#3182ce;">#<?= $b['id'] ?></strong></td>
                                <td><?= htmlspecialchars($b['nama_depan'] . ' ' . $b['nama_belakang']) ?><br><small style="color:#718096;"><?= htmlspecialchars($b['no_wa']) ?></small></td>
                                <td>
                                    <strong><?= htmlspecialchars($b['nama_ruang'] ?? 'Ruangan Dihapus') ?></strong><br>
                                    <small style="color:#2b6cb0;">Kat: <?= htmlspecialchars($b['nama_kategori'] ?? '-') ?></small><br>
                                    <small style="color:#4a5568;">Tarif: Rp <?= number_format((float)($b['tarif_per_jam'] ?? 0), 0, ',', '.') ?>/jam</small>
                                </td>
                                <td><?= htmlspecialchars($b['pesan_untuk_tanggal']) ?><br><small style="color:#718096;">Jam: <?= htmlspecialchars($b['jam_mulai']) ?> (Durasi: <?= $b['durasi'] ?>)</small></td>
                                <td>
                                    <?php if (!empty($b['bukti_pembayaran'])): ?>
                                        <a href="uploads/<?= htmlspecialchars($b['bukti_pembayaran']) ?>" target="_blank" style="color: #3182ce; font-weight:600;">Lihat Bukti</a>
                                    <?php else: ?>
                                        <span style="color:#e53e3e; font-size:12px; font-weight:600;">Belum Upload</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="approve_booking">
                                            <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                            <button type="submit" class="btn btn-success">Approve</button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="reject_booking">
                                            <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                            <button type="submit" class="btn btn-danger">Reject</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center; color: #a0aec0; padding: 18px;">Tidak ada request booking pending.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

       
        <!-- SECTION 1.5: APPROVED BOOKINGS LIST -->
        <div class="card" style="flex-basis: 100%;">
            <h3 style="color: #28a745;">List Approved Bookings (Menunggu Kedatangan)</h3>
            <div class="table-responsive">
                <table>
                    <tr><th>ID</th><th>Pemesan & WA</th><th>Ruangan</th><th>Tanggal & Jam Main</th><th>Aksi</th></tr>
                    <?php if (count($approvedBookings) > 0): ?>
                        <?php foreach ($approvedBookings as $ab): ?>
                        <tr>
                            <td>#<?= $ab['id'] ?></td>
                            <td><?= htmlspecialchars($ab['nama_depan'] . ' ' . $ab['nama_belakang']) ?><br><small><?= htmlspecialchars($ab['no_wa']) ?></small></td>
                            <td><?= htmlspecialchars($ab['nama_ruang']) ?></td>
                            <td><?= htmlspecialchars($ab['pesan_untuk_tanggal']) ?> | <?= htmlspecialchars($ab['jam_mulai']) ?> (<?= $ab['durasi'] ?>)</td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Tandai pelanggan ini sudah datang ke lokasi?');">
                                    <input type="hidden" name="action" value="mark_arrived">
                                    <input type="hidden" name="booking_id" value="<?= $ab['id'] ?>">
                                    <button type="submit" class="btn-primary" style="background-color: #17a2b8;">Telah Datang</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center;">Belum ada booking yang disetujui.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- SECTION 1.6: LOG BOOK (TERKONFIRMASI DATANG) -->
        <div class="card" style="flex-basis: 100%; border-top: 4px solid #6c757d;">
            <h3 style="color: #6c757d;">Log Book (Histori Penggunaan Ruangan)</h3>
            <div class="table-responsive">
                <table>
                    <tr><th>ID</th><th>Pemesan & WA</th><th>Ruangan</th><th>Waktu Bermain</th><th>Status</th></tr>
                    <?php if (count($logBookings) > 0): ?>
                        <?php foreach ($logBookings as $log): ?>
                        <tr style="background-color: #f8f9fa;">
                            <td>#<?= $log['id'] ?></td>
                            <td><?= htmlspecialchars($log['nama_depan'] . ' ' . $log['nama_belakang']) ?></td>
                            <td><?= htmlspecialchars($log['nama_ruang'] ?? 'Ruangan Telah Dihapus') ?></td>
                            <td><?= htmlspecialchars($log['pesan_untuk_tanggal']) ?> (<?= $log['durasi'] ?>)</td>
                            <td><span style="background: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8em;">Selesai</span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center;">Belum ada histori log book.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <!-- SECTION KATEGORI & RUANGAN -->
        <div class="row-grid">
            
            <!-- SECTION 2: KATEGORI RUANG -->
            <div class="card">
                <h3 id="formTitle_katRuang" style="color: #3182ce;">Kelola Kategori Ruang</h3>
                <form method="POST">
                    <input type="hidden" name="action" id="action_katRuang" value="add_kategori_ruang">
                    <input type="hidden" name="edit_id" id="id_katRuang" value="">
                    
                    <label>Nama Kategori</label>
                    <input type="text" name="nama" id="nama_katRuang" placeholder="Contoh: VVIP, VIP, Reguler" required>
                    
                    <label>Deskripsi</label>
                    <textarea name="deskripsi" id="desk_katRuang" placeholder="Deskripsi kategori ruang..." required></textarea>
                    
                    <button type="submit" class="btn btn-primary" id="btn_katRuang">Simpan Kategori</button>
                    <button type="button" class="btn btn-warning" style="width:100%; margin-top:5px; display:none;" id="btnCancel_katRuang" onclick="cancelEdit('katRuang')">Batal Edit</button>
                </form>

                <hr>

                <h4>List Kategori Ruang</h4>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr><th>NAMA</th><th>DESKRIPSI</th><th>AKSI</th></tr>
                        </thead>
                        <tbody>
                            <?php if (count($listKategori) > 0): ?>
                                <?php foreach ($listKategori as $kat): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($kat['nama']) ?></strong></td>
                                    <td class="desc-text"><?= !empty($kat['deskripsi']) ? htmlspecialchars($kat['deskripsi']) : '-' ?></td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn btn-warning" onclick="editForm('katRuang', <?= $kat['id'] ?>, '<?= htmlspecialchars(addslashes($kat['nama'])) ?>', '<?= htmlspecialchars(addslashes($kat['deskripsi'])) ?>')">Edit</button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus kategori ini?');">
                                                <input type="hidden" name="action" value="delete_kategori_ruang">
                                                <input type="hidden" name="delete_id" value="<?= $kat['id'] ?>">
                                                <button type="submit" class="btn btn-danger">Del</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" style="text-align:center; color:#a0aec0;">Belum ada data kategori ruang.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SECTION 3: RUANGAN -->
            <div class="card">
                <h3 id="formTitle_ruang" style="color: #3182ce;">Kelola Data Ruangan</h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="action_ruang" value="add_ruang">
                    <input type="hidden" name="edit_id" id="id_ruang" value="">
                    
                    <label>Nama Ruangan</label>
                    <input type="text" name="nama" id="nama_ruang" placeholder="Contoh: Ruang Playstation 5 VIP A" required>
                    
                    <div style="display: flex; gap: 10px;">
                        <div style="flex:1;">
                            <label>Jumlah Unit</label>
                            <input type="number" name="jumlah_unit" id="qty_ruang" required value="1" min="1">
                        </div>
                        <div style="flex:1;">
                            <label>Kategori Ruang</label>
                            <select name="kategori_id" id="kat_ruang" required>
                                <option value="">-- Pilih --</option>
                                <?php foreach ($listKategori as $kat): ?>
                                    <option value="<?= $kat['id'] ?>"><?= htmlspecialchars($kat['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <label>Tarif Per Jam (Rp)</label>
                    <input type="number" name="tarif" id="tarif_ruang" placeholder="Contoh: 50000" required>
                    
                    <label>Deskripsi Ruangan</label>
                    <textarea name="deskripsi" id="desk_ruang" placeholder="Fasilitas AC, Sofa, TV 55 Inch, dll." required></textarea>
                    
                    <label>Foto Ruangan</label>
                    <input type="file" name="foto" accept="image/*">
                    
                    <button type="submit" class="btn btn-primary" id="btn_ruang">Simpan Ruangan</button>
                    <button type="button" class="btn btn-warning" style="width:100%; margin-top:5px; display:none;" id="btnCancel_ruang" onclick="cancelEdit('ruang')">Batal Edit</button>
                </form>

                <hr>

                <h4>List Ruangan (Semua Atribut)</h4>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>FOTO</th>
                                <th>NAMA RUANGAN</th>
                                <th>KATEGORI</th>
                                <th>JUMLAH UNIT</th>
                                <th>TARIF / JAM</th>
                                <th>DESKRIPSI</th>
                                <th>AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($listRuang) > 0): ?>
                                <?php foreach ($listRuang as $r): ?>
                                <tr>
                                    <td>
                                        <?php if(!empty($r['foto'])): ?>
                                            <img src="uploads/<?= htmlspecialchars($r['foto']) ?>" class="thumb">
                                        <?php else: ?>
                                            <small style="color:#a0aec0;">No Foto</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?= htmlspecialchars($r['nama']) ?></strong></td>
                                    <td>
                                        <span class="badge-kat">
                                            <?= htmlspecialchars($mapKategoriRuang[$r['kategori_ruang'] ?? 0] ?? 'Tanpa Kategori') ?>
                                        </span>
                                    </td>
                                    <td><?= (int)($r['jumlah_unit'] ?? 1) ?> Unit</td>
                                    <td style="color:#3182ce; font-weight:600;">Rp <?= number_format((float)$r['tarif_per_jam'], 0, ',', '.') ?></td>
                                    <td class="desc-text"><?= !empty($r['deskripsi']) ? htmlspecialchars($r['deskripsi']) : '-' ?></td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn btn-warning" onclick="editRuang(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['nama'])) ?>', <?= $r['jumlah_unit'] ?>, <?= $r['kategori_ruang'] ?? 0 ?>, <?= $r['tarif_per_jam'] ?>, '<?= htmlspecialchars(addslashes($r['deskripsi'])) ?>')">Edit</button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus ruangan ini?');">
                                                <input type="hidden" name="action" value="delete_ruang">
                                                <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
                                                <button type="submit" class="btn btn-danger">Del</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" style="text-align:center; color:#a0aec0;">Belum ada data ruangan.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- SECTION KATEGORI UNIT & UNIT -->
        <div class="row-grid">
            
            <!-- SECTION 4: KATEGORI UNIT -->
            <div class="card">
                <h3 id="formTitle_katUnit" style="color:#805ad5;">Kelola Kategori Unit</h3>
                <form method="POST">
                    <input type="hidden" name="action" id="action_katUnit" value="add_kategori_unit">
                    <input type="hidden" name="edit_id" id="id_katUnit" value="">
                    
                    <label>Nama Kategori Unit</label>
                    <input type="text" name="nama" id="nama_katUnit" placeholder="Contoh: Stick, Headset, Console" required>
                    
                    <label>Deskripsi</label>
                    <textarea name="deskripsi" id="desk_katUnit" placeholder="Deskripsi kategori unit..." required></textarea>
                    
                    <button type="submit" class="btn btn-primary" style="background-color: #805ad5;" id="btn_katUnit">Simpan Kategori Unit</button>
                    <button type="button" class="btn btn-warning" style="width:100%; margin-top:5px; display:none;" id="btnCancel_katUnit" onclick="cancelEdit('katUnit')">Batal Edit</button>
                </form>

                <hr>

                <h4>List Kategori Unit</h4>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr><th>NAMA</th><th>DESKRIPSI</th><th>AKSI</th></tr>
                        </thead>
                        <tbody>
                            <?php if (count($listKategoriUnit) > 0): ?>
                                <?php foreach ($listKategoriUnit as $kat): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($kat['nama']) ?></strong></td>
                                    <td class="desc-text"><?= !empty($kat['deskripsi']) ? htmlspecialchars($kat['deskripsi']) : '-' ?></td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn btn-warning" onclick="editForm('katUnit', <?= $kat['id'] ?>, '<?= htmlspecialchars(addslashes($kat['nama'])) ?>', '<?= htmlspecialchars(addslashes($kat['deskripsi'])) ?>')">Edit</button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus kategori unit ini?');">
                                                <input type="hidden" name="action" value="delete_kategori_unit">
                                                <input type="hidden" name="delete_id" value="<?= $kat['id'] ?>">
                                                <button type="submit" class="btn btn-danger">Del</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" style="text-align:center; color:#a0aec0;">Belum ada data kategori unit.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SECTION 5: UNIT -->
            <div class="card">
                <h3 id="formTitle_unit" style="color:#805ad5;">Kelola Data Unit / Barang</h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="action_unit" value="add_unit">
                    <input type="hidden" name="edit_id" id="id_unit" value="">
                    
                    <label>Nama Unit / Barang</label>
                    <input type="text" name="nama" id="nama_unit" placeholder="Contoh: DualSense Wireless Controller PS5" required>
                    
                    <div style="display: flex; gap: 10px;">
                        <div style="flex:1;">
                            <label>Stock / Jumlah</label>
                            <input type="number" name="jumlah_unit" id="qty_unit" required value="1" min="0">
                        </div>
                        <div style="flex:1;">
                            <label>Kategori Unit</label>
                            <select name="kategori_id" id="kat_unit" required>
                                <option value="">-- Pilih --</option>
                                <?php foreach ($listKategoriUnit as $kat): ?>
                                    <option value="<?= $kat['id'] ?>"><?= htmlspecialchars($kat['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <label>Deskripsi Unit</label>
                    <textarea name="deskripsi" id="desk_unit" placeholder="Spesifikasi atau kondisi unit..." required></textarea>
                    
                    <label>Foto Unit</label>
                    <input type="file" name="foto" accept="image/*">
                    
                    <button type="submit" class="btn btn-primary" style="background-color: #805ad5;" id="btn_unit">Simpan Unit</button>
                    <button type="button" class="btn btn-warning" style="width:100%; margin-top:5px; display:none;" id="btnCancel_unit" onclick="cancelEdit('unit')">Batal Edit</button>
                </form>

                <hr>

                <h4>List Unit (Semua Atribut)</h4>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>FOTO</th>
                                <th>NAMA UNIT</th>
                                <th>KATEGORI</th>
                                <th>STOCK</th>
                                <th>DESKRIPSI</th>
                                <th>AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($listUnit) > 0): ?>
                                <?php foreach ($listUnit as $u): ?>
                                <tr>
                                    <td>
                                        <?php if(!empty($u['foto'])): ?>
                                            <img src="uploads/<?= htmlspecialchars($u['foto']) ?>" class="thumb">
                                        <?php else: ?>
                                            <small style="color:#a0aec0;">No Foto</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?= htmlspecialchars($u['nama']) ?></strong></td>
                                    <td>
                                        <span class="badge-kat" style="background-color: #faf5ff; color: #6b46c1; border-color: #e9d8fd;">
                                            <?= htmlspecialchars($mapKategoriUnit[$u['kategori_unit'] ?? 0] ?? 'Tanpa Kategori') ?>
                                        </span>
                                    </td>
                                    <td><?= (int)($u['jumlah_unit'] ?? 0) ?> Item</td>
                                    <td class="desc-text"><?= !empty($u['deskripsi']) ? htmlspecialchars($u['deskripsi']) : '-' ?></td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn btn-warning" onclick="editUnit(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['nama'])) ?>', <?= $u['jumlah_unit'] ?>, <?= $u['kategori_unit'] ?? 0 ?>, '<?= htmlspecialchars(addslashes($u['deskripsi'])) ?>')">Edit</button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus unit ini?');">
                                                <input type="hidden" name="action" value="delete_unit">
                                                <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-danger">Del</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align:center; color:#a0aec0;">Belum ada data unit.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>

    <!-- JAVASCRIPT UNTUK EDIT & CANCEL -->
    <script>
        function editForm(type, id, nama, deskripsi) {
            document.getElementById('formTitle_' + type).innerText = 'Edit Kategori';
            document.getElementById('action_' + type).value = 'edit_kategori_' + (type === 'katRuang' ? 'ruang' : 'unit');
            document.getElementById('id_' + type).value = id;
            document.getElementById('nama_' + type).value = nama;
            document.getElementById('desk_' + type).value = deskripsi;
            document.getElementById('btn_' + type).innerText = 'Update Kategori';
            document.getElementById('btnCancel_' + type).style.display = 'block';
            document.getElementById('formTitle_' + type).scrollIntoView({behavior: "smooth"});
        }

        function editRuang(id, nama, qty, katId, tarif, deskripsi) {
            document.getElementById('formTitle_ruang').innerText = 'Edit Data Ruangan';
            document.getElementById('action_ruang').value = 'edit_ruang';
            document.getElementById('id_ruang').value = id;
            document.getElementById('nama_ruang').value = nama;
            document.getElementById('qty_ruang').value = qty;
            document.getElementById('kat_ruang').value = katId;
            document.getElementById('tarif_ruang').value = tarif;
            document.getElementById('desk_ruang').value = deskripsi;
            document.getElementById('btn_ruang').innerText = 'Update Ruangan';
            document.getElementById('btnCancel_ruang').style.display = 'block';
            document.getElementById('formTitle_ruang').scrollIntoView({behavior: "smooth"});
        }

        function editUnit(id, nama, qty, katId, deskripsi) {
            document.getElementById('formTitle_unit').innerText = 'Edit Data Unit';
            document.getElementById('action_unit').value = 'edit_unit';
            document.getElementById('id_unit').value = id;
            document.getElementById('nama_unit').value = nama;
            document.getElementById('qty_unit').value = qty;
            document.getElementById('kat_unit').value = katId;
            document.getElementById('desk_unit').value = deskripsi;
            document.getElementById('btn_unit').innerText = 'Update Unit';
            document.getElementById('btnCancel_unit').style.display = 'block';
            document.getElementById('formTitle_unit').scrollIntoView({behavior: "smooth"});
        }

        function cancelEdit(type) {
            if(type === 'katRuang') {
                document.getElementById('formTitle_katRuang').innerText = 'Kelola Kategori Ruang';
                document.getElementById('action_katRuang').value = 'add_kategori_ruang';
                document.getElementById('btn_katRuang').innerText = 'Simpan Kategori';
            } else if(type === 'katUnit') {
                document.getElementById('formTitle_katUnit').innerText = 'Kelola Kategori Unit';
                document.getElementById('action_katUnit').value = 'add_kategori_unit';
                document.getElementById('btn_katUnit').innerText = 'Simpan Kategori Unit';
            } else if(type === 'ruang') {
                document.getElementById('formTitle_ruang').innerText = 'Kelola Data Ruangan';
                document.getElementById('action_ruang').value = 'add_ruang';
                document.getElementById('btn_ruang').innerText = 'Simpan Ruangan';
            } else if(type === 'unit') {
                document.getElementById('formTitle_unit').innerText = 'Kelola Data Unit / Barang';
                document.getElementById('action_unit').value = 'add_unit';
                document.getElementById('btn_unit').innerText = 'Simpan Unit';
            }

            document.getElementById('id_' + type).value = '';
            document.getElementById('nama_' + type).value = '';
            if(document.getElementById('desk_' + type)) document.getElementById('desk_' + type).value = '';
            if(document.getElementById('qty_' + type)) document.getElementById('qty_' + type).value = '1';
            if(document.getElementById('kat_' + type)) document.getElementById('kat_' + type).value = '';
            if(document.getElementById('tarif_' + type)) document.getElementById('tarif_' + type).value = '';
            document.getElementById('btnCancel_' + type).style.display = 'none';
        }
    </script>
</body>
</html>