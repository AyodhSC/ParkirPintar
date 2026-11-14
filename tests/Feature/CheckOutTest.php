<?php

namespace Tests\Feature;

use App\Models\ParkingArea;
use App\Models\ParkingRecord;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckOutTest extends TestCase
{
    use RefreshDatabase;

    private function makeArea(string $name = 'Gedung Tes', int $motor = 2, int $mobil = 1): ParkingArea
    {
        return ParkingArea::create([
            'name' => $name,
            'capacity_motor' => $motor,
            'capacity_mobil' => $mobil,
        ]);
    }

    private function makeActiveRecord(ParkingArea $area, string $plate, string $type, CarbonInterface $checkInAt): ParkingRecord
    {
        return ParkingRecord::create([
            'parking_area_id' => $area->id,
            'plate_number' => $plate,
            'vehicle_type' => $type,
            'check_in_at' => $checkInAt,
            'status' => 'ACTIVE',
        ]);
    }

    private function checkIn(ParkingArea $area, string $plate, string $type = 'motor'): TestResponse
    {
        return $this->postJson('/api/check-in', [
            'plate_number' => $plate,
            'vehicle_type' => $type,
            'parking_area_id' => $area->id,
        ]);
    }

    private function checkOut(string $plate): TestResponse
    {
        return $this->postJson('/api/check-out', ['plate_number' => $plate]);
    }

    #[DataProvider('feeCases')]
    public function test_check_out_stores_fee_and_marks_done(string $type, string $checkInAt, int $expectedFee): void
    {
        $area = $this->makeArea();
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00'));
        $this->makeActiveRecord($area, 'B 1234 XYZ', $type, Carbon::parse($checkInAt));

        $this->checkOut('B 1234 XYZ')
            ->assertOk()
            ->assertJsonPath('data.status', 'DONE')
            ->assertJsonPath('data.fee', $expectedFee);

        $this->assertDatabaseHas('parking_records', [
            'plate_number' => 'B 1234 XYZ',
            'status' => 'DONE',
            'fee' => $expectedFee,
        ]);
    }

    public static function feeCases(): array
    {
        return [
            'motor 10 menit gratis'          => ['motor', '2026-11-14 09:50:00', 0],
            'motor 2,5 jam'                  => ['motor', '2026-11-14 07:30:00', 4000],
            'motor 20 jam kena batas harian' => ['motor', '2026-11-13 14:00:00', 20000],
            'mobil 90 menit'                 => ['mobil', '2026-11-14 08:30:00', 8000],
            'mobil 20 jam kena batas harian' => ['mobil', '2026-11-13 14:00:00', 50000],
        ];
    }

    public function test_rejects_check_out_without_active_record(): void
    {
        $this->checkOut('B 9999 ZZZ')->assertNotFound();
    }

    public function test_rejects_second_check_out_for_same_visit(): void
    {
        $area = $this->makeArea();
        $this->makeActiveRecord($area, 'B 1234 XYZ', 'motor', now()->subHour());

        $this->checkOut('B 1234 XYZ')->assertOk();
        $this->checkOut('B 1234 XYZ')->assertNotFound();

        $this->assertDatabaseCount('parking_records', 1);
    }

    public function test_check_out_frees_capacity(): void
    {
        $area = $this->makeArea(motor: 1);

        $this->checkIn($area, 'B 1111 AAA')->assertCreated();
        $this->checkIn($area, 'B 2222 BBB')->assertStatus(409);

        $this->checkOut('B 1111 AAA')->assertOk();

        $this->checkIn($area, 'B 2222 BBB')->assertCreated();
    }

    public function test_plate_number_is_normalized_on_check_out(): void
    {
        $area = $this->makeArea();
        $this->makeActiveRecord($area, 'B 1234 XYZ', 'motor', now()->subHour());

        $this->checkOut(' b  1234 xyz ')->assertOk();
    }

    public function test_validates_input(): void
    {
        $this->postJson('/api/check-out', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['plate_number']);

        $this->checkOut(str_repeat('A', 21))->assertStatus(422);
    }
}
