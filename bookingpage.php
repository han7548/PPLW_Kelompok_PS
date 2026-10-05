<?php
require_once __DIR__ . '/callinglibs.php';

$tanggal = $_GET['tanggal'] ?? '';
$jam_mulai = $_GET['jam_mulai'] ?? '';
$durasi = $_GET['durasi'] ?? '';

$ruangDipilih = null;
$ruangTersedia = [];
$error = '';

/* ==================================================
 * 1. USER MEMILIH RUANGAN
 * ================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pilih_ruangan') {
    $ruangDipilih = [
        'id' => (int) ($_POST['ruang_id'] ?? 0),
        'tanggal' => trim($_POST['tanggal'] ?? ''),
        'jam_mulai' => trim($_POST['jam_mulai'] ?? ''),
        'durasi' => (int) ($_POST['durasi'] ?? 0)
    ];
}

/* ==================================================
 * 2. USER MEMBUAT REQUEST BOOKING
 * ================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'buat_booking') {
    $nama_depan = trim($_POST['nama_depan'] ?? '');
    $nama_belakang = trim($_POST['nama_belakang'] ?? '');
    $no_wa = trim($_POST['no_wa'] ?? '');
    $ruang_id = (int) ($_POST['ruang_id'] ?? 0);
    $tanggal_booking = trim($_POST['tanggal'] ?? '');
    $jam_booking = trim($_POST['jam_mulai'] ?? '');
    $durasi_booking = (int) ($_POST['durasi'] ?? 0);

    if ($nama_depan === '') {
        $error = 'Nama depan harus diisi.';
    } elseif ($nama_belakang === '') {
        $error = 'Nama belakang harus diisi.';
    } elseif ($no_wa === '') {
        $error = 'Nomor WhatsApp harus diisi.';
    } elseif ($ruang_id <= 0) {
        $error = 'Ruangan belum dipilih.';
    } elseif ($tanggal_booking === '') {
        $error = 'Tanggal booking belum dipilih.';
    } elseif ($jam_booking === '') {
        $error = 'Jam mulai belum dipilih.';
    } elseif ($durasi_booking < 1 || $durasi_booking > 12) {
        $error = 'Durasi harus antara 1 sampai 12 jam.';
    } else {
        // Simpan data di session sementara, JANGAN masuk ke database dulu
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['pending_booking'] = [
            'nama_depan' => $nama_depan,
            'nama_belakang' => $nama_belakang,
            'no_wa' => $no_wa,
            'ruang_id' => $ruang_id,
            'tanggal' => $tanggal_booking,
            'jam_mulai' => $jam_booking,
            'durasi' => $durasi_booking
        ];
        header("Location: PagePembayaran.php");
        exit();
    }
}

/* ==================================================
 * 3. MENCARI RUANGAN TERSEDIA
 * ================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['tanggal']) && isset($_GET['jam_mulai']) && isset($_GET['durasi'])) {
    $tanggal = trim($_GET['tanggal']);
    $jam_mulai = trim($_GET['jam_mulai']);
    $durasi = (int) $_GET['durasi'];

    if ($tanggal === '') {
        $error = 'Tanggal harus dipilih.';
    } elseif ($jam_mulai === '') {
        $error = 'Jam mulai harus dipilih.';
    } elseif ($durasi < 1 || $durasi > 12) {
        $error = 'Durasi harus antara 1 sampai 12 jam.';
    } elseif ($tanggal < date('Y-m-d')) {
        $error = 'Tanggal tidak boleh sebelum hari ini.';
    } else {
        try {
            $db = new DBconnection();
            $ruang = new Ruang($db);
            $ruangTersedia = $ruang->cariRuangTersedia($tanggal, $jam_mulai, $durasi);
            $db->close_connection();
        } catch (Throwable $e) {
            $error = 'Terjadi kesalahan saat mencari ruangan: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Ruangan - Rental PS</title>
    <!-- Import Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --success: #10b981;
            --success-hover: #059669;
            --bg-color: #f3f4f6;
            --card-bg: #ffffff;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background: var(--bg-color); 
            color: var(--text-main); 
            padding: 20px; 
            max-width: 1000px; 
            margin: 0 auto; 
            line-height: 1.5;
        }

        .header-nav { margin-bottom: 25px; }
        .nav-back { 
            display: inline-flex; align-items: center; text-decoration: none; 
            color: var(--primary); font-weight: 600; font-size: 0.95em;
        }
        .nav-back:hover { text-decoration: underline; }

        /* Container Styles */
        .glass-container { 
            background: var(--card-bg); padding: 30px; border-radius: 12px; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); 
            margin-bottom: 30px; border: 1px solid var(--border-color);
        }
        
        .section-title { font-size: 1.25em; font-weight: 700; color: var(--text-main); margin: 0 0 20px 0; border-bottom: 2px solid var(--bg-color); padding-bottom: 10px; }

        /* Form Grid - Horizontal on Desktop */
        .form-row { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: end; }
        .form-group label { display: block; font-size: 0.85em; font-weight: 600; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        input[type="date"], input[type="time"], input[type="number"], input[type="text"] { 
            width: 100%; padding: 12px 15px; border: 1px solid var(--border-color); 
            border-radius: 8px; font-size: 1em; outline: none; transition: border-color 0.2s; font-family: inherit;
        }
        input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        
        /* Buttons */
        .btn { padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 1em; cursor: pointer; border: none; transition: all 0.2s; text-align: center; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-1px); }
        .btn-success { background: var(--success); color: white; width: 100%; margin-top: 15px; }
        .btn-success:hover { background: var(--success-hover); }

        /* Cards Grid */
        .room-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-top: 20px; }
        .room-card { 
            background: var(--card-bg); padding: 25px; border-radius: 12px; 
            border: 1px solid var(--border-color); display: flex; flex-direction: column;
            transition: all 0.3s ease; position: relative; overflow: hidden;
        }
        .room-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border-color: var(--primary); }
        .room-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: var(--primary); }
        
        .room-title { font-size: 1.2em; font-weight: 700; margin: 0 0 10px 0; color: var(--text-main); }
        .badge { display: inline-block; padding: 4px 10px; font-size: 0.75em; font-weight: 700; border-radius: 20px; background: #e0e7ff; color: #3730a3; margin-bottom: 12px; }
        .room-desc { font-size: 0.9em; color: var(--text-muted); flex-grow: 1; margin-bottom: 15px; }
        .price-row { display: flex; justify-content: space-between; align-items: center; padding-top: 15px; border-top: 1px dashed var(--border-color); margin-bottom: 15px; }
        .price-label { font-size: 0.85em; color: var(--text-muted); }
        .price-value { font-size: 1.1em; font-weight: 700; color: var(--primary); }
        
        /* Alerts */
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; font-weight: 600; font-size: 0.95em; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        /* Responsive */
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; gap: 12px; }
            .btn-primary { width: 100%; margin-top: 10px; }
        }
    </style>
</head>
<body>

    <div class="header-nav">
        <a href="dashboard.php" class="nav-back">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 5px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Home
        </a>
    </div>

    <!-- BAGIAN 1: FORM PENCARIAN RUANGAN -->
    <div class="glass-container">
        <h3 class="section-title">Cari Ruangan Kosong</h3>
        <form action="bookingpage.php" method="GET">
            <div class="form-row">
                <div class="form-group">
                    <label for="tanggal">Tanggal Main</label>
                    <input type="date" id="tanggal" name="tanggal" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($tanggal) ?>" required>
                </div>
                <div class="form-group">
                    <label for="jam_mulai">Jam Mulai</label>
                    <input type="time" id="jam_mulai" name="jam_mulai" value="<?= htmlspecialchars($jam_mulai) ?>" required>
                </div>
                <div class="form-group">
                    <label for="durasi">Durasi (Jam)</label>
                    <input type="number" id="durasi" name="durasi" min="1" max="12" placeholder="Contoh: 2" value="<?= htmlspecialchars($durasi) ?>" required>
                </div>
                <button type="submit" class="btn btn-primary">Cek Ketersediaan</button>
            </div>
        </form>
    </div>

    <!-- PESAN ERROR -->
    <?php if ($error !== ''): ?>
        <div class="alert alert-error">
            ⚠️ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- BAGIAN 2: HASIL PENCARIAN RUANGAN -->
    <?php if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['tanggal']) && isset($_GET['jam_mulai']) && isset($_GET['durasi']) && $error === ''): ?>
        <div class="results-wrapper">
            <h4 style="color: var(--text-muted); font-size: 0.95em; margin-bottom: 15px;">
                Tersedia untuk: <strong><?= htmlspecialchars($tanggal) ?></strong> pukul <strong><?= htmlspecialchars($jam_mulai) ?></strong> (<?= htmlspecialchars($durasi) ?> Jam)
            </h4>

            <?php if (count($ruangTersedia) > 0): ?>
                <div class="room-grid">
                    <?php foreach ($ruangTersedia as $ruangData): ?>
                        <?php $totalHarga = (float) $ruangData['tarif_per_jam'] * $durasi; ?>
                        <div class="room-card">
                            <?php if(!empty($ruangData['foto'])): ?>
                                <img src="uploads/<?= htmlspecialchars($ruangData['foto']) ?>" style="width:100%; height:140px; object-fit:cover; border-radius:8px; margin-bottom:15px;">
                            <?php endif; ?>
                            <span class="badge"><?= htmlspecialchars($ruangData['nama_kategori'] ?? 'Umum') ?></span>
                            <h5 class="room-title"><?= htmlspecialchars($ruangData['nama']) ?></h5>
                            <p class="room-desc"><?= htmlspecialchars($ruangData['deskripsi']) ?></p>
                            
                            <div class="price-row">
                                <span class="price-label">Tarif Dasar</span>
                                <span>Rp <?= number_format((float) $ruangData['tarif_per_jam'], 0, ',', '.') ?> / jam</span>
                            </div>
                            <div class="price-row" style="border: none; padding-top: 0;">
                                <span class="price-label" style="font-weight: 600; color: var(--text-main);">Total Bayar</span>
                                <span class="price-value">Rp <?= number_format($totalHarga, 0, ',', '.') ?></span>
                            </div>

                            <form action="bookingpage.php" method="POST" style="margin-top: auto;">
                                <input type="hidden" name="action" value="pilih_ruangan">
                                <input type="hidden" name="ruang_id" value="<?= htmlspecialchars($ruangData['id']) ?>">
                                <input type="hidden" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>">
                                <input type="hidden" name="jam_mulai" value="<?= htmlspecialchars($jam_mulai) ?>">
                                <input type="hidden" name="durasi" value="<?= htmlspecialchars($durasi) ?>">
                                <button type="submit" class="btn btn-primary" style="width: 100%;">Pilih Ruangan Ini</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="glass-container" style="text-align: center; color: var(--text-muted); padding: 40px;">
                    <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-bottom: 10px; opacity: 0.5;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <p>Maaf, tidak ada ruangan yang kosong pada jadwal tersebut.<br>Silakan pilih tanggal atau jam lain.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- BAGIAN 3: FORM DATA PEMESAN -->
    <?php if ($ruangDipilih !== null): ?>
        <div class="glass-container" style="margin-top: 30px; border-top: 4px solid var(--success);">
            <h3 class="section-title">Lengkapi Data Pemesan</h3>
            
            <div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9em; color: var(--text-muted);">
                <strong>Ringkasan Pesanan:</strong> Ruangan #<?= htmlspecialchars($ruangDipilih['id']) ?> | <?= htmlspecialchars($ruangDipilih['tanggal']) ?> | Jam <?= htmlspecialchars($ruangDipilih['jam_mulai']) ?> | Durasi <?= htmlspecialchars($ruangDipilih['durasi']) ?> Jam
            </div>
            
            <form action="bookingpage.php" method="POST">
                <input type="hidden" name="action" value="buat_booking">
                <input type="hidden" name="ruang_id" value="<?= htmlspecialchars($ruangDipilih['id']) ?>">
                <input type="hidden" name="tanggal" value="<?= htmlspecialchars($ruangDipilih['tanggal']) ?>">
                <input type="hidden" name="jam_mulai" value="<?= htmlspecialchars($ruangDipilih['jam_mulai']) ?>">
                <input type="hidden" name="durasi" value="<?= htmlspecialchars($ruangDipilih['durasi']) ?>">

                <div class="form-row" style="grid-template-columns: 1fr 1fr;">
                    <div class="form-group">
                        <label for="nama_depan">Nama Depan</label>
                        <input type="text" id="nama_depan" name="nama_depan" required>
                    </div>
                    <div class="form-group">
                        <label for="nama_belakang">Nama Belakang</label>
                        <input type="text" id="nama_belakang" name="nama_belakang" required>
                    </div>
                </div>
                <div class="form-group" style="margin-top: 15px;">
                    <label for="no_wa">Nomor WhatsApp Aktif</label>
                    <input type="text" id="no_wa" name="no_wa" maxlength="14" placeholder="Contoh: 08123456789" required>
                </div>
                <button type="submit" class="btn btn-success">Lanjutkan ke Pembayaran</button>
            </form>
        </div>
    <?php endif; ?>

</body>
</html>