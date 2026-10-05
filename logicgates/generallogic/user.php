<?php
class User {
    private DBconnection $db;

    public function __construct(DBconnection $db) {
        $this->db = $db;
    }

    public function loginAdmin(string $username, string $password): Respon {
        $query = "SELECT id, username, password, nama_lengkap, role FROM users WHERE username = $1 AND role = 'admin'";
        $respon = $this->db->send_query($query, [$username]);

        if ($respon->status && !empty($respon->data)) {
            $user = $respon->data[0];
            
            if (password_verify($password, $user['password'])) {
                if (session_status() === PHP_SESSION_NONE) session_start();
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_name'] = $user['nama_lengkap'];
                return new Respon(true, "Login berhasil", $user);
            }
        }
        return new Respon(false, "Username atau password salah.");
    }
}
?>