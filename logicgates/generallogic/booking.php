<?php
class Booking {
    private DBconnection $db;

    public function __construct(DBconnection $db) {
        $this->db = $db;
    }

    // Required by bookingpage.php
    public function buatRequestBooking(string $nd, string $nb, string $wa, int $r_id, string $tgl, string $jam, int $durasi): Respon {
        $query = "INSERT INTO request_booking (nama_depan, nama_belakang, no_wa, ruang_id, pesan_untuk_tanggal, jam_mulai, durasi, approval_status) 
                  VALUES ($1, $2, $3, $4, $5, $6, $7 * INTERVAL '1 hour', 'pending')";
        return $this->db->send_query($query, [$nd, $nb, $wa, $r_id, $tgl, $jam, $durasi]);
    }

    // Used by AdminControlPanel
    public function getAllPending(): Respon {
        return $this->db->send_query("SELECT * FROM request_booking WHERE approval_status = 'pending' ORDER BY created_at ASC");
    }

    public function updateStatus(int $id, string $status, int $admin_id) {
    // TAMBAHKAN 'terkonfirmasi datang' KE DALAM ARRAY INI
    $allowed_statuses = ['pending', 'approved', 'rejected', 'terkonfirmasi datang'];
    
    if (!in_array($status, $allowed_statuses)) {
        return new Respon(false, "Status tidak valid.");
    }

    $query = "UPDATE request_booking SET approval_status = $1, updated_by = $2 WHERE id = $3";
    return $this->db->send_query($query, [$status, $admin_id, $id]);
}
}
?>