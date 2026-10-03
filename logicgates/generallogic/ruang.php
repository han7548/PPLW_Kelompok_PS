<?php

class Ruang {
    private $db;

    public function __construct() {
        // Mengubungkan ke Database otomatis saat class diinstansiasi
        $this->db = new DbConnection();
    }

    public function getAllRuang() {
        // Logika query SELECT * FROM ruang menggunakan $this->db
        return [];
    }

    public function getRuangById($id) {
        // Logika query SELECT * FROM ruang WHERE id = $id
        return null;
    }
}