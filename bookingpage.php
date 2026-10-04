<?php
// display kalender seperti contoh, pilih tanggal, start hour, sewa berapa jam dari argumen tsb kita shows ruangan apa aja yang available,

// booking card ( informasi tanggal, jam mulai, sewa berapa jam dan jenis ruangan auto terisi)

// janlup respon request booking telah selesai dibuat, akan segera dikonfirmasi oleh admin dan chat by wa trims
require_once __DIR__ . '/callinglibs.php';

$tanggal = $_GET['tanggal'] ?? '';
$jam_mulai = $_GET['jam_mulai'] ?? '';
$durasi = $_GET['durasi'] ?? '';

$ruangDipilih = null;
$ruangTersedia = [];

$success = '';
$error = '';

/*
 * ==================================================
 * PROSES POST
 * ==================================================
 *
 * Ada 2 kemungkinan:
 *
 * 1. pilih_ruangan
 *    User memilih salah satu ruangan.
 *
 * 2. buat_booking
 *    User mengisi data pemesan lalu membuat
 *    request booking ke database.
 *
 */


/*
 * ==================================================
 * 1. USER MEMILIH RUANGAN
 * ==================================================
 */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'pilih_ruangan'
) {

    $ruangDipilih = [
        'id' => (int) ($_POST['ruang_id'] ?? 0),

        'tanggal' => trim(
            $_POST['tanggal'] ?? ''
        ),

        'jam_mulai' => trim(
            $_POST['jam_mulai'] ?? ''
        ),

        'durasi' => (int) (
            $_POST['durasi'] ?? 0
        )
    ];
}


/*
 * ==================================================
 * 2. USER MEMBUAT REQUEST BOOKING
 * ==================================================
 */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'buat_booking'
) {

    $nama_depan = trim(
        $_POST['nama_depan'] ?? ''
    );

    $nama_belakang = trim(
        $_POST['nama_belakang'] ?? ''
    );

    $no_wa = trim(
        $_POST['no_wa'] ?? ''
    );

    $ruang_id = (int) (
        $_POST['ruang_id'] ?? 0
    );

    $tanggal_booking = trim(
        $_POST['tanggal'] ?? ''
    );

    $jam_booking = trim(
        $_POST['jam_mulai'] ?? ''
    );

    $durasi_booking = (int) (
        $_POST['durasi'] ?? 0
    );


    /*
     * Validasi data pemesan
     */

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
    } elseif (
        $durasi_booking < 1 ||
        $durasi_booking > 12
    ) {

        $error = 'Durasi harus antara 1 sampai 12 jam.';
    } else {

        try {

            /*
             * Membuat koneksi database
             */

            $db = new DBconnection();


            /*
             * Membuat object Booking
             */

            $booking = new Booking($db);


            /*
             * Membuat request booking
             */

            $respon = $booking->buatRequestBooking(
                $nama_depan,
                $nama_belakang,
                $no_wa,
                $ruang_id,
                $tanggal_booking,
                $jam_booking,
                $durasi_booking
            );


            /*
             * Mengecek apakah INSERT berhasil
             */

            if ($respon->status) {

                $success =
                    'Request booking telah selesai dibuat. '
                    . 'Booking akan segera dikonfirmasi oleh admin '
                    . 'dan admin akan menghubungi Anda melalui WhatsApp.';
            } else {

                $error =
                    'Request booking gagal dibuat: '
                    . $respon->message;
            }


            $db->close_connection();
        } catch (Throwable $e) {

            $error =
                'Terjadi kesalahan saat membuat booking: '
                . $e->getMessage();
        }
    }
}


/*
 * ==================================================
 * 3. MENCARI RUANGAN TERSEDIA
 * ==================================================
 *
 * Hanya dilakukan ketika user melakukan pencarian
 * menggunakan GET.
 *
 */

if (
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    isset($_GET['tanggal']) &&
    isset($_GET['jam_mulai']) &&
    isset($_GET['durasi'])
) {

    $tanggal = trim(
        $_GET['tanggal']
    );

    $jam_mulai = trim(
        $_GET['jam_mulai']
    );

    $durasi = (int) $_GET['durasi'];


    /*
     * Validasi pencarian
     */

    if ($tanggal === '') {

        $error = 'Tanggal harus dipilih.';
    } elseif ($jam_mulai === '') {

        $error = 'Jam mulai harus dipilih.';
    } elseif (
        $durasi < 1 ||
        $durasi > 12
    ) {

        $error =
            'Durasi harus antara 1 sampai 12 jam.';
    } elseif (
        $tanggal < date('Y-m-d')
    ) {

        $error =
            'Tanggal tidak boleh sebelum hari ini.';
    } else {

        try {

            /*
             * Membuat koneksi database
             */

            $db = new DBconnection();


            /*
             * Membuat object Ruang
             */

            $ruang = new Ruang($db);


            /*
             * Mencari ruangan yang tersedia
             */

            $ruangTersedia =
                $ruang->cariRuangTersedia(
                    $tanggal,
                    $jam_mulai,
                    $durasi
                );


            $db->close_connection();
        } catch (Throwable $e) {

            $error =
                'Terjadi kesalahan saat mencari ruangan: '
                . $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Booking Ruangan</title>

</head>


<body>


    <!--
==================================================
BAGIAN 1
FORM PENCARIAN RUANGAN
==================================================
-->

    <div class="search-container">

        <h3>
            Cari Ruangan Kosong
        </h3>


        <form
            action="bookingpage.php"
            method="GET">


            <div class="form-group">

                <label for="tanggal">
                    Tanggal Main
                </label>


                <input
                    type="date"
                    id="tanggal"
                    name="tanggal"
                    min="<?= date('Y-m-d') ?>"
                    value="<?= htmlspecialchars($tanggal) ?>"
                    required>

            </div>


            <div class="form-group">

                <label for="jam_mulai">
                    Jam Mulai
                </label>


                <input
                    type="time"
                    id="jam_mulai"
                    name="jam_mulai"
                    value="<?= htmlspecialchars($jam_mulai) ?>"
                    required>

            </div>


            <div class="form-group">

                <label for="durasi">
                    Durasi (Jam)
                </label>


                <input
                    type="number"
                    id="durasi"
                    name="durasi"
                    min="1"
                    max="12"
                    placeholder="Contoh: 2"
                    value="<?= htmlspecialchars($durasi) ?>"
                    required>

            </div>


            <button type="submit">
                Cari Ketersediaan
            </button>


        </form>

    </div>


    <!--
==================================================
PESAN ERROR
==================================================
-->

    <?php if ($error !== ''): ?>

        <div class="alert-error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!--
==================================================
PESAN BERHASIL
==================================================
-->

    <?php if ($success !== ''): ?>

        <div class="alert-success">

            <h3>
                Booking Berhasil
            </h3>

            <p>
                <?= htmlspecialchars($success) ?>
            </p>

        </div>

    <?php endif; ?>


    <!--
==================================================
BAGIAN 2
HASIL PENCARIAN RUANGAN
==================================================
-->

    <?php if (
        $_SERVER['REQUEST_METHOD'] === 'GET' &&
        isset($_GET['tanggal']) &&
        isset($_GET['jam_mulai']) &&
        isset($_GET['durasi']) &&
        $error === ''
    ): ?>


        <div class="room-results-container">


            <h4>
                Ruangan tersedia
            </h4>


            <p>

                Tanggal:

                <strong>

                    <?= htmlspecialchars($tanggal) ?>

                </strong>

            </p>


            <p>

                Jam mulai:

                <strong>

                    <?= htmlspecialchars($jam_mulai) ?>

                </strong>

            </p>


            <p>

                Durasi:

                <strong>

                    <?= htmlspecialchars($durasi) ?>

                    jam

                </strong>

            </p>


            <?php if (count($ruangTersedia) > 0): ?>


                <div class="room-grid">


                    <?php foreach (
                        $ruangTersedia
                        as $ruangData
                    ): ?>


                        <?php

                        $totalHarga =
                            (float)
                            $ruangData['tarif_per_jam']
                            * $durasi;

                        ?>


                        <div class="room-card">


                            <!-- Nama ruangan -->

                            <h5>

                                <?= htmlspecialchars(
                                    $ruangData['nama']
                                ) ?>

                            </h5>


                            <!-- Jenis ruangan -->

                            <p>

                                Jenis Ruangan:

                                <strong>

                                    <?= htmlspecialchars(
                                        $ruangData['nama_kategori']
                                            ?? 'Tidak ada kategori'
                                    ) ?>

                                </strong>

                            </p>


                            <!-- Deskripsi -->

                            <p>

                                <?= htmlspecialchars(
                                    $ruangData['deskripsi']
                                ) ?>

                            </p>


                            <!-- Tarif -->

                            <p>

                                Tarif:

                                <strong>

                                    Rp

                                    <?= number_format(
                                        (float)
                                        $ruangData['tarif_per_jam'],
                                        0,
                                        ',',
                                        '.'
                                    ) ?>

                                    / jam

                                </strong>

                            </p>


                            <hr>


                            <!--
                        ==========================================
                        BOOKING CARD
                        ==========================================
                        -->

                            <h4>
                                Detail Booking
                            </h4>


                            <p>

                                Tanggal:

                                <?= htmlspecialchars(
                                    $tanggal
                                ) ?>

                            </p>


                            <p>

                                Jam Mulai:

                                <?= htmlspecialchars(
                                    $jam_mulai
                                ) ?>

                            </p>


                            <p>

                                Durasi:

                                <?= htmlspecialchars(
                                    $durasi
                                ) ?>

                                jam

                            </p>


                            <p>

                                Jenis Ruangan:

                                <?= htmlspecialchars(
                                    $ruangData['nama_kategori']
                                        ?? 'Tidak ada kategori'
                                ) ?>

                            </p>


                            <p>

                                <strong>

                                    Total:

                                    Rp

                                    <?= number_format(
                                        $totalHarga,
                                        0,
                                        ',',
                                        '.'
                                    ) ?>

                                </strong>

                            </p>


                            <!--
                        ==========================================
                        PILIH RUANGAN
                        ==========================================
                        -->

                            <form
                                action="bookingpage.php"
                                method="POST">


                                <input
                                    type="hidden"
                                    name="action"
                                    value="pilih_ruangan">


                                <input
                                    type="hidden"
                                    name="ruang_id"
                                    value="<?= htmlspecialchars(
                                                $ruangData['id']
                                            ) ?>">


                                <input
                                    type="hidden"
                                    name="tanggal"
                                    value="<?= htmlspecialchars(
                                                $tanggal
                                            ) ?>">


                                <input
                                    type="hidden"
                                    name="jam_mulai"
                                    value="<?= htmlspecialchars(
                                                $jam_mulai
                                            ) ?>">


                                <input
                                    type="hidden"
                                    name="durasi"
                                    value="<?= htmlspecialchars(
                                                $durasi
                                            ) ?>">


                                <button type="submit">

                                    Pilih Ruangan Ini

                                </button>


                            </form>


                        </div>


                    <?php endforeach; ?>


                </div>


            <?php else: ?>


                <p>

                    Maaf, tidak ada ruangan yang kosong
                    pada jadwal tersebut.

                    Silakan pilih tanggal atau jam lain.

                </p>


            <?php endif; ?>


        </div>


    <?php endif; ?>


    <!--
==================================================
BAGIAN 3
FORM DATA PEMESAN
==================================================
-->

    <?php if ($ruangDipilih !== null): ?>


        <div class="booking-form-container">


            <h3>
                Data Pemesan
            </h3>


            <p>
                Silakan isi data untuk membuat
                request booking.
            </p>


            <form
                action="bookingpage.php"
                method="POST">


                <input
                    type="hidden"
                    name="action"
                    value="buat_booking">


                <input
                    type="hidden"
                    name="ruang_id"
                    value="<?= htmlspecialchars(
                                $ruangDipilih['id']
                            ) ?>">


                <input
                    type="hidden"
                    name="tanggal"
                    value="<?= htmlspecialchars(
                                $ruangDipilih['tanggal']
                            ) ?>">


                <input
                    type="hidden"
                    name="jam_mulai"
                    value="<?= htmlspecialchars(
                                $ruangDipilih['jam_mulai']
                            ) ?>">


                <input
                    type="hidden"
                    name="durasi"
                    value="<?= htmlspecialchars(
                                $ruangDipilih['durasi']
                            ) ?>">


                <div class="form-group">


                    <label for="nama_depan">

                        Nama Depan

                    </label>


                    <input
                        type="text"
                        id="nama_depan"
                        name="nama_depan"
                        required>


                </div>


                <div class="form-group">


                    <label for="nama_belakang">

                        Nama Belakang

                    </label>


                    <input
                        type="text"
                        id="nama_belakang"
                        name="nama_belakang"
                        required>


                </div>


                <div class="form-group">


                    <label for="no_wa">

                        Nomor WhatsApp

                    </label>


                    <input
                        type="text"
                        id="no_wa"
                        name="no_wa"
                        maxlength="14"
                        required>


                </div>


                <button type="submit">

                    Buat Request Booking

                </button>


            </form>


        </div>


    <?php endif; ?>


</body>

</html>