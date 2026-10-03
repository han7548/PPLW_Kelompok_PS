<?php

class Unit {
    private $db;

    public function __construct() {
        // Mengubungkan ke Database otomatis saat class diinstansiasi
        $this->db = new DbConnection();
    }

    public function getAllUnit() {
        // Logika query SELECT * FROM unit menggunakan $this->db
        return [];
    }
}