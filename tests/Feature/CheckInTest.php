<?php

namespace Tests\Feature;

use App\Models\ParkingArea;
use App\Models\ParkingRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CheckInTest extends TestCase
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

    private function checkIn(ParkingArea $area, string $plate, string $type = 'motor'): TestResponse
    {
        return $this->postJson('/api/check-in', [
            'plate_number' => $plate,
            'vehicle_type' => $type,
            'parking_area_id' => $area->id,
        ]);
    }

    public function test_check_in_creates_active_record(): void
    {
        $area = $this->makeArea();

        $this->checkIn($area, 'B 1234 XYZ')->assertCreated();

        $this->assertDatabaseHas('parking_records', [
            'parking_area_id' => $area->id,
            'plate_number' => 'B 1234 XYZ',
            'vehicle_type' => 'motor',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_plate_number_is_normalized(): void
    {
        $area = $this->makeArea();

        $this->checkIn($area, ' b  1234 xyz ')->assertCreated();

        $this->assertDatabaseHas('parking_records', ['plate_number' => 'B 1234 XYZ']);
    }

    public function test_rejects_plate_that_is_still_inside(): void
    {
        $area = $this->makeArea();

        $this->checkIn($area, 'B 1234 XYZ')->assertCreated();
        $this->checkIn($area, 'B 1234 XYZ')->assertStatus(409);

        $this->assertDatabaseCount('parking_records', 1);
    }

    public function test_plate_can_check_in_again_after_done(): void
    {
        $area = $this->makeArea();

        ParkingRecord::create([
            'parking_area_id' => $area->id,
            'plate_number' => 'B 1234 XYZ',
            'vehicle_type' => 'motor',
            'check_in_at' => now()->subHours(2),
            'check_out_at' => now()->subHour(),
            'fee' => 3000,
            'status' => 'DONE',
        ]);

        $this->checkIn($area, 'B 1234 XYZ')->assertCreated();
    }

    public function test_rejects_when_area_is_full_for_vehicle_type(): void
    {
        $area = $this->makeArea(motor: 1);

        $this->checkIn($area, 'B 1111 AAA')->assertCreated();
        $this->checkIn($area, 'B 2222 BBB')->assertStatus(409);

        $this->assertDatabaseCount('parking_records', 1);
    }

    public function test_capacity_is_counted_per_vehicle_type(): void
    {
        $area = $this->makeArea(motor: 1, mobil: 1);

        $this->checkIn($area, 'B 1111 AAA', 'motor')->assertCreated();
        $this->checkIn($area, 'B 2222 BBB', 'mobil')->assertCreated();
    }

    public function test_capacity_is_counted_per_area(): void
    {
        $areaA = $this->makeArea('Gedung A', motor: 1);
        $areaB = $this->makeArea('Gedung B', motor: 1);

        $this->checkIn($areaA, 'B 1111 AAA')->assertCreated();
        $this->checkIn($areaB, 'B 2222 BBB')->assertCreated();
    }

    public function test_validates_input(): void
    {
        $this->postJson('/api/check-in', [
            'plate_number' => str_repeat('A', 21),
            'vehicle_type' => 'sepeda',
            'parking_area_id' => 9999,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['plate_number', 'vehicle_type', 'parking_area_id']);
    }

    public function test_plate_number_boundary_is_20_characters(): void
    {
        $area = $this->makeArea();

        $this->checkIn($area, str_repeat('A', 20))->assertCreated();
        $this->checkIn($area, str_repeat('B', 21))->assertStatus(422);
    }
}
