<?php
// class User: urusan akun (sementara baru login)
// otomatis dimuat oleh autoloader di callinglibs.php

class User {
    private DBconnection $db;

    public function __construct(DBconnection $db) {
        $this->db = $db;
    }

    public function login(string $username, string $password): Respon {
        $result = $this->db->send_query(
            'SELECT id, username, password, nama_lengkap, role FROM users WHERE username = $1 LIMIT 1',
            [$username]
        );

        // query gagal (DB error): detail dicatat di log, user cukup lihat pesan umum
        if (!$result->status) {
            error_log('User::login query gagal: ' . $result->message);
            return new Respon(false, 'Terjadi kesalahan server. Coba lagi nanti.');
        }

        // username tidak ketemu ATAU password salah -> pesan sengaja disamakan
        if (empty($result->data) || !password_verify($password, $result->data[0]['password'])) {
            return new Respon(false, 'Username atau password salah.');
        }

        return new Respon(true, 'Login berhasil.', [
            'id'           => $result->data[0]['id'],
            'username'     => $result->data[0]['username'],
            'nama_lengkap' => $result->data[0]['nama_lengkap'],
            'role'         => $result->data[0]['role'],
        ]);
    }
}