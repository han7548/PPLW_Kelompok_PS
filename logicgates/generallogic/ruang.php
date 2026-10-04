<?php

// bikin class ruang 
class Ruang
{
    private DBconnection $db;

    public function __construct(DBconnection $db)
    {
        $this->db = $db;
    }

    /**
     * Mencari ruangan yang tersedia berdasarkan:
     * - tanggal
     * - jam mulai
     * - durasi sewa
     */
    public function cariRuangTersedia(
        string $tanggal,
        string $jam_mulai,
        int $durasi_jam
    ): array {

        $query = "
            SELECT
                r.id,
                r.nama,
                r.jumlah_unit,
                r.kategori_ruang,
                r.tarif_per_jam,
                r.is_active,
                r.deskripsi,
                kr.nama AS nama_kategori
            FROM ruang r
            LEFT JOIN kategori_ruang kr
                ON r.kategori_ruang = kr.id
            WHERE r.is_active = TRUE

            AND NOT EXISTS (
                SELECT 1
                FROM request_booking rb
                WHERE rb.ruang_id = r.id
                AND rb.pesan_untuk_tanggal = $1

                AND rb.approval_status IN ('pending', 'approved')

                AND (
                    $2::TIME < (rb.jam_mulai + rb.durasi)

                    AND

                    (
                        $2::TIME
                        + ($3 * INTERVAL '1 hour')
                    ) > rb.jam_mulai
                )
            )

            ORDER BY
                r.kategori_ruang ASC,
                r.tarif_per_jam ASC
        ";

        $respon = $this->db->send_query(
            $query,
            [
                $tanggal,
                $jam_mulai,
                $durasi_jam
            ]
        );

        if (!$respon->status) {
            return [];
        }

        return $respon->data;
    }
}

// CRUD YA GAIS KARENA BIAR ADMIN JUGA BISA MODIF BY CONTROL PANEL
