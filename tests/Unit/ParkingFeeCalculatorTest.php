<?php

namespace Tests\Unit;

use App\Services\ParkingFeeCalculator;
use Carbon\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ParkingFeeCalculatorTest extends TestCase
{
    #[DataProvider('feeCases')]
    public function test_calculates_fee(string $type, int $seconds, int $expected): void
    {
        $checkIn = Carbon::parse('2026-11-14 08:00:00');
        $checkOut = $checkIn->copy()->addSeconds($seconds);

        $fee = (new ParkingFeeCalculator())->calculate($type, $checkIn, $checkOut);

        $this->assertSame($expected, $fee);
    }

    public static function feeCases(): array
    {
        $min = 60;
        $hour = 3600;

        return [
            // Motor: Rp2.000 jam pertama, Rp1.000 per jam berikutnya, maks Rp20.000/hari
            'motor 0 menit gratis'           => ['motor', 0, 0],
            'motor tepat 15 menit gratis'    => ['motor', 15 * $min, 0],
            'motor 15 menit 1 detik'         => ['motor', 15 * $min + 1, 2000],
            'motor tepat 1 jam'              => ['motor', $hour, 2000],
            'motor 61 menit dibulatkan 2 jam' => ['motor', 61 * $min, 3000],
            'motor tepat 2 jam'              => ['motor', 2 * $hour, 3000],
            'motor 2 jam 1 menit'            => ['motor', 2 * $hour + $min, 4000],
            'motor 19 jam pas batas harian'  => ['motor', 19 * $hour, 20000],
            'motor 20 jam kena batas harian' => ['motor', 20 * $hour, 20000],
            'motor tepat 24 jam'             => ['motor', 24 * $hour, 20000],
            'motor 25 jam'                   => ['motor', 25 * $hour, 22000],

            // Mobil: Rp5.000 jam pertama, Rp3.000 per jam berikutnya, maks Rp50.000/hari
            'mobil tepat 15 menit gratis'    => ['mobil', 15 * $min, 0],
            'mobil 16 menit'                 => ['mobil', 16 * $min, 5000],
            'mobil tepat 1 jam'              => ['mobil', $hour, 5000],
            'mobil 61 menit dibulatkan 2 jam' => ['mobil', 61 * $min, 8000],
            'mobil 5 jam'                    => ['mobil', 5 * $hour, 17000],
            'mobil 16 jam pas batas harian'  => ['mobil', 16 * $hour, 50000],
            'mobil 20 jam kena batas harian' => ['mobil', 20 * $hour, 50000],
            'mobil tepat 24 jam'             => ['mobil', 24 * $hour, 50000],
            'mobil 25 jam'                   => ['mobil', 25 * $hour, 55000],
        ];
    }

    public function test_rejects_unknown_vehicle_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $checkIn = Carbon::parse('2026-11-14 08:00:00');
        (new ParkingFeeCalculator())->calculate('sepeda', $checkIn, $checkIn->copy()->addHour());
    }

    public function test_rejects_checkout_before_checkin(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $checkIn = Carbon::parse('2026-11-14 08:00:00');
        (new ParkingFeeCalculator())->calculate('motor', $checkIn, $checkIn->copy()->subMinute());
    }
}
