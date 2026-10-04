<?php
// bikin class booking, kalo ini baru ada create
class booking
{
    private DBconnection $db;

    public function __construct(DBconnection $db)
    {
        $this->db = $db;
    }

    /**
     * Membuat request booking baru.
     */
    public function buatRequestBooking(
        string $nama_depan,
        string $nama_belakang,
        string $no_wa,
        int $ruang_id,
        string $tanggal,
        string $jam_mulai,
        int $durasi
    ): Respon {

        $query = "
            INSERT INTO request_booking
            (
                nama_depan,
                nama_belakang,
                no_wa,
                ruang_id,
                pesan_untuk_tanggal,
                jam_mulai,
                durasi,
                approval_status
            )
            VALUES
            (
                $1,
                $2,
                $3,
                $4,
                $5,
                $6,
                $7 * INTERVAL '1 hour',
                'pending'
            )
            RETURNING id
        ";

        return $this->db->send_query(
            $query,
            [
                $nama_depan,
                $nama_belakang,
                $no_wa,
                $ruang_id,
                $tanggal,
                $jam_mulai,
                $durasi
            ]
        );
    }
}
