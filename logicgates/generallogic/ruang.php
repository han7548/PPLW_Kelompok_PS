<?php
class Ruang {
    private DBconnection $db;

    public function __construct(DBconnection $db) {
        $this->db = $db;
    }

    public function getAll(): Respon {
        $query = "SELECT r.*, kr.nama as nama_kategori FROM ruang r LEFT JOIN kategori_ruang kr ON r.kategori_ruang = kr.id";
        return $this->db->send_query($query);
    }

        public function cariRuangTersedia(string $tanggal, string $jam_mulai, int $durasi): array {
        $query = "
            SELECT r.id, r.nama, r.deskripsi, r.tarif_per_jam, r.foto, kr.nama as nama_kategori
            FROM ruang r
            LEFT JOIN kategori_ruang kr ON r.kategori_ruang = kr.id
            WHERE r.is_active = true
            AND r.id NOT IN (
                SELECT ruang_id 
                FROM request_booking
                WHERE pesan_untuk_tanggal = $1
                AND approval_status IN ('approved', 'pending')
                AND (
                    $2::time < (jam_mulai + durasi) 
                    AND 
                    ($2::time + ($3 || ' hours')::interval) > jam_mulai
                )
            )";
        
        $respon = $this->db->send_query($query, [$tanggal, $jam_mulai, $durasi]);
        return $respon->status ? $respon->data : [];
    }

    public function create(
        string $nama,
        int $jumlahUnit,
        int $kategoriId,
        string $deskripsi,
        ?string $foto,
        int $adminId
        ): Respon {
        $query = "INSERT INTO unit (nama, jumlah_unit, kategori_unit, deskripsi, foto, created_by)
                  VALUES ($1, $2, $3, $4, $5, $6)";
        return $this->db->send_query($query, [
            $nama,
            $jumlahUnit,
            $kategoriId > 0 ? $kategoriId : null,
            $deskripsi,
            $foto,
            $adminId,
        ]);
    }
 
    public function update(
        int $id,
        string $nama,
        int $jumlahUnit,
        int $kategoriId,
        string $deskripsi,
        ?string $foto,
        int $adminId
        ): Respon {
        $query = "UPDATE unit
                  SET nama = $1, jumlah_unit = $2, kategori_unit = $3, deskripsi = $4,
                      foto = COALESCE($5::varchar, foto), updated_by = $6
                  WHERE id = $7";
        return $this->db->send_query($query, [
            $nama,
            $jumlahUnit,
            $kategoriId > 0 ? $kategoriId : null,
            $deskripsi,
            $foto,
            $adminId,
            $id,
        ]);
    }
 
    public function delete(int $id): Respon {
        return $this->db->send_query("DELETE FROM unit WHERE id = $1", [$id]);
    }
 
    public function setActive(int $id, bool $aktif, int $adminId): Respon {
        $query = "UPDATE unit SET is_active = $1::boolean, updated_by = $2 WHERE id = $3";
        return $this->db->send_query($query, [$aktif ? 'true' : 'false', $adminId, $id]);
    }
}
?>