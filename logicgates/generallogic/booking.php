<?php

class Booking {
    private $db;

    public function __construct() {
        // Mengubungkan ke Database otomatis saat class diinstansiasi
        $this->db = new DbConnection();
    }

    public function createBooking($namaDepan, $namaBelakang, $noWa, $ruangId, $tanggal, $jamMulai, $durasi) {
        if (empty($namaDepan) || empty($noWa) || empty($ruangId) || empty($tanggal)) {
            return new Respon(false, "Data booking tidak lengkap!");
        }

        // Logika query INSERT ke database menggunakan $this->db dimasukkan di sini
        
        return new Respon(true, "Booking berhasil dibuat!");
    }
}