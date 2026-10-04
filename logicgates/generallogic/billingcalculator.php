<?php
// class BillingCalculator: hitung biaya sewa.
// Tarif TIDAK di-hardcode di sini: tarif diambil dari kolom ruang.tarif_per_jam
// lalu dikirim ke method ini (supaya selalu sama dengan tarif yang diatur admin).

class BillingCalculator {

    public function calculateRentalCost(float $tarifPerJam, float $durationHours): float
    {
        if ($tarifPerJam < 0) {
            throw new InvalidArgumentException('Tarif tidak boleh negatif.');
        }
        if ($durationHours <= 0) {
            throw new InvalidArgumentException('Durasi harus lebih dari 0 jam.');
        }
        return $tarifPerJam * $durationHours;
    }

    public function calculateFinalBill(float $tarifPerJam, float $durationHours): array
    {
        $rentalCost = $this->calculateRentalCost($tarifPerJam, $durationHours);

        return [
            'tarif_per_jam' => $tarifPerJam,
            'durasi_jam'    => $durationHours,
            'rental_cost'   => $rentalCost,
            'total'         => $rentalCost, // nanti bisa ditambah diskon/biaya lain
        ];
    }
}