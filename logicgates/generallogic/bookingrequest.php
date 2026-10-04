<?php
// class BookingRequest: validasi input booking + cek ruang/jadwal + hitung total.
// Dipakai bersama oleh pembayaran.php (sebelum bayar) dan process_booking.php (saat kirim),
// supaya aturannya selalu sama di kedua tempat. Gagal = Respon(false, pesan, ['http' => kode]).

class BookingRequest {
    public const DURASI_MIN = 1;
    public const DURASI_MAX = 12;

    private static function gagal(string $pesan, int $http = 422): Respon
    {
        return new Respon(false, $pesan, ['http' => $http]);
    }

    private static function val(array $post, string $key, string $default = ''): string
    {
        $v = $post[$key] ?? $default;
        return is_string($v) ? trim($v) : '';
    }

    // 1. cek format input; sukses -> data rapi di $respon->data
    public static function validate(array $post): Respon
    {
        date_default_timezone_set('Asia/Jakarta');

        $nama_depan    = self::val($post, 'nama_depan');
        $nama_belakang = self::val($post, 'nama_belakang');
        $no_wa         = preg_replace('/[\s\-]/', '', self::val($post, 'no_wa'));
        $tanggal       = self::val($post, 'pesan_untuk_tanggal') ?: self::val($post, 'pesan_tanggal');
        $jam_mulai     = self::val($post, 'jam_mulai');
        $ruang_id      = filter_var(self::val($post, 'ruang_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $durasi_jam    = filter_var(self::val($post, 'durasi', '1'), FILTER_VALIDATE_INT,
                                    ['options' => ['min_range' => self::DURASI_MIN, 'max_range' => self::DURASI_MAX]]);

        if ($nama_depan === '' || mb_strlen($nama_depan) > 255 || mb_strlen($nama_belakang) > 255) {
            return self::gagal('Nama depan wajib diisi (maksimal 255 karakter).');
        }
        if (!preg_match('/^\+?[0-9]{9,13}$/', $no_wa)) { // kolom no_wa hanya 14 karakter
            return self::gagal('Nomor WhatsApp tidak valid.');
        }
        if ($ruang_id === false) {
            return self::gagal('Silakan pilih ruangan.');
        }
        if ($durasi_jam === false) {
            return self::gagal('Durasi harus angka ' . self::DURASI_MIN . ' sampai ' . self::DURASI_MAX . ' jam.');
        }

        $tgl = DateTime::createFromFormat('!Y-m-d', $tanggal);
        if (!$tgl || $tgl->format('Y-m-d') !== $tanggal) {
            return self::gagal('Format tanggal tidak valid.');
        }
        if ($tgl < new DateTime('today')) {
            return self::gagal('Tanggal booking tidak boleh di masa lalu.');
        }
        $jam = DateTime::createFromFormat('!H:i', $jam_mulai);
        if (!$jam || $jam->format('H:i') !== $jam_mulai) {
            return self::gagal('Format jam mulai tidak valid (contoh 14:00).');
        }
        if ((clone $tgl)->setTime((int)$jam->format('H'), (int)$jam->format('i')) < new DateTime('now')) {
            return self::gagal('Jam mulai sudah lewat. Pilih jam yang masih ke depan.');
        }

        $mulai   = (int)$jam->format('H') * 60 + (int)$jam->format('i');
        $selesai = $mulai + $durasi_jam * 60;
        if ($selesai > 24 * 60) {
            return self::gagal('Booking harus selesai sebelum tengah malam.');
        }

        return new Respon(true, 'OK', [
            'nama_depan' => $nama_depan, 'nama_belakang' => $nama_belakang, 'no_wa' => $no_wa,
            'ruang_id' => $ruang_id, 'tanggal' => $tanggal, 'jam_mulai' => $jam_mulai,
            'durasi_jam' => $durasi_jam, 'mulai_menit' => $mulai, 'selesai_menit' => $selesai,
            'jam_selesai' => sprintf('%02d:%02d', intdiv($selesai, 60), $selesai % 60),
        ]);
    }

    // 2. cek ruang aktif + jadwal tidak bentrok + hitung total. Sukses -> info ruang & total.
    public static function check(DBconnection $db, array $d): Respon
    {
        $ruang = $db->send_query(
            'SELECT r.id, r.nama, r.tarif_per_jam, kr.nama AS kategori
               FROM ruang r LEFT JOIN kategori_ruang kr ON kr.id = r.kategori_ruang
              WHERE r.id = $1 AND r.is_active = TRUE',
            [$d['ruang_id']]
        );
        if (!$ruang->status) {
            error_log('BookingRequest cek ruang: ' . $ruang->message);
            return self::gagal('Terjadi kesalahan server. Coba lagi nanti.', 500);
        }
        if (empty($ruang->data)) {
            return self::gagal('Ruangan tidak ditemukan atau sedang tidak aktif.', 404);
        }

        // bentrok dengan booking yang sudah disetujui (APPROVED) atau sedang berjalan (ACTIVE)
        $bentrok = $db->send_query(
            'SELECT 1 FROM request_booking
              WHERE ruang_id = $1 AND pesan_untuk_tanggal = $2 AND UPPER(approval_status) IN (\'APPROVED\', \'ACTIVE\')
                AND EXTRACT(EPOCH FROM jam_mulai) / 60 < $3
                AND (EXTRACT(EPOCH FROM jam_mulai) + EXTRACT(EPOCH FROM durasi)) / 60 > $4
              LIMIT 1',
            [$d['ruang_id'], $d['tanggal'], $d['selesai_menit'], $d['mulai_menit']]
        );
        if (!$bentrok->status) {
            error_log('BookingRequest cek bentrok: ' . $bentrok->message);
            return self::gagal('Terjadi kesalahan server. Coba lagi nanti.', 500);
        }
        if (!empty($bentrok->data)) {
            return self::gagal('Ruangan sudah terisi di jam tersebut. Silakan pilih jam atau ruangan lain.', 409);
        }

        $tarif   = (float)($ruang->data[0]['tarif_per_jam'] ?? 0);
        $billing = (new BillingCalculator())->calculateFinalBill($tarif, $d['durasi_jam']);

        return new Respon(true, 'OK', [
            'ruang_nama' => $ruang->data[0]['nama'],
            'kategori'   => $ruang->data[0]['kategori'] ?? '-',
            'tarif'      => $tarif,
            'total'      => $billing['total'],
        ]);
    }
}