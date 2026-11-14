<?php

namespace App\Services;

use Carbon\CarbonInterface;
use InvalidArgumentException;

class ParkingFeeCalculator
{
    private const FREE_SECONDS = 15 * 60;
    private const HOUR_SECONDS = 3600;
    private const DAY_SECONDS = 86400;

    private const RATES = [
        'motor' => ['first' => 2000, 'next' => 1000, 'daily_max' => 20000],
        'mobil' => ['first' => 5000, 'next' => 3000, 'daily_max' => 50000],
    ];

    public function calculate(string $vehicleType, CarbonInterface $checkIn, CarbonInterface $checkOut): int
    {
        if (! isset(self::RATES[$vehicleType])) {
            throw new InvalidArgumentException("Jenis kendaraan tidak dikenal: {$vehicleType}");
        }

        $seconds = $checkOut->getTimestamp() - $checkIn->getTimestamp();

        if ($seconds < 0) {
            throw new InvalidArgumentException('Waktu keluar tidak boleh lebih awal dari waktu masuk.');
        }

        if ($seconds <= self::FREE_SECONDS) {
            return 0;
        }

        $rate = self::RATES[$vehicleType];
        $fullDays = intdiv($seconds, self::DAY_SECONDS);
        $remainder = $seconds % self::DAY_SECONDS;

        return $fullDays * $rate['daily_max'] + $this->feeForPartialDay($remainder, $rate);
    }

    private function feeForPartialDay(int $seconds, array $rate): int
    {
        if ($seconds === 0) {
            return 0;
        }

        $hours = (int) ceil($seconds / self::HOUR_SECONDS);
        $fee = $rate['first'] + ($hours - 1) * $rate['next'];

        return min($fee, $rate['daily_max']);
    }
}
