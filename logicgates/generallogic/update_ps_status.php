<?php
// class update_ps_status: status ruang + siklus sesi booking (APPROVED -> ACTIVE -> COMPLETED).
// Nama class & method SAMA dengan versi awal. Yang berubah: memakai DBconnection (pg_*), bukan PDO.
// Status di approval_status ditulis HURUF BESAR: PENDING, APPROVED, ACTIVE, COMPLETED.

class update_ps_status {

    private DBconnection $db;

    public function __construct(DBconnection $database) {
        $this->db = $database;
    }

    // Jalankan query. Kalau DB error: catat ke log lalu lempar exception,
    // supaya error tidak disalahartikan sebagai "ruang penuh" atau "0 sesi diperbarui".
    private function run(string $sql, array $params = []): array
    {
        $res = $this->db->send_query($sql, $params);
        if (!$res->status) {
            error_log('update_ps_status: ' . $res->message);
            throw new RuntimeException('Gagal memproses status ruang.');
        }
        return $res->data;
    }

    // true = ruang aktif DAN sedang tidak dipakai (jam dibandingkan dalam waktu Jakarta)
    public function isRuangTersedia(int $ruangId): bool
    {
        $ruang = $this->run('SELECT 1 FROM ruang WHERE id = $1 AND is_active = TRUE', [$ruangId]);
        if (empty($ruang)) {
            return false;
        }

        $dipakai = $this->run(
            'SELECT 1 FROM request_booking
              WHERE ruang_id = $1
                AND UPPER(approval_status) IN (\'APPROVED\', \'ACTIVE\')
                AND (NOW() AT TIME ZONE \'Asia/Jakarta\') >= (pesan_untuk_tanggal + jam_mulai)
                AND (NOW() AT TIME ZONE \'Asia/Jakarta\') <  (pesan_untuk_tanggal + jam_mulai + durasi)
              LIMIT 1',
            [$ruangId]
        );
        return empty($dipakai);
    }

    // APPROVED -> ACTIVE. true hanya kalau memang ada booking yang berubah.
    public function startBookingSession(int $bookingId): bool
    {
        $rows = $this->run(
            'UPDATE request_booking SET approval_status = \'ACTIVE\'
              WHERE id = $1 AND UPPER(approval_status) = \'APPROVED\'
              RETURNING id',
            [$bookingId]
        );
        return !empty($rows);
    }

    // ACTIVE -> COMPLETED. true hanya kalau memang ada booking yang berubah.
    public function finishBookingSession(int $bookingId): bool
    {
        $rows = $this->run(
            'UPDATE request_booking SET approval_status = \'COMPLETED\'
              WHERE id = $1 AND UPPER(approval_status) = \'ACTIVE\'
              RETURNING id',
            [$bookingId]
        );
        return !empty($rows);
    }

    // Tutup semua sesi ACTIVE yang waktunya sudah lewat. Return: jumlah sesi yang ditutup.
    public function autoUpdateExpiredSessions(): int
    {
        $rows = $this->run(
            'UPDATE request_booking SET approval_status = \'COMPLETED\'
              WHERE UPPER(approval_status) = \'ACTIVE\'
                AND (pesan_untuk_tanggal + jam_mulai + durasi) <= (NOW() AT TIME ZONE \'Asia/Jakarta\')
              RETURNING id'
        );
        return count($rows);
    }
}