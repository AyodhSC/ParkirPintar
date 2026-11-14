<?php

namespace Tests\Feature;

use App\Models\ParkingArea;
use App\Models\ParkingRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ParkingAreaTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Gedung A',
            'capacity_motor' => 50,
            'capacity_mobil' => 20,
        ], $override);
    }

    private function makeArea(string $name = 'Gedung A', int $motor = 2, int $mobil = 1): ParkingArea
    {
        return ParkingArea::create([
            'name' => $name,
            'capacity_motor' => $motor,
            'capacity_mobil' => $mobil,
        ]);
    }

    private function addActiveRecord(ParkingArea $area, string $plate, string $type = 'motor'): ParkingRecord
    {
        return ParkingRecord::create([
            'parking_area_id' => $area->id,
            'plate_number' => $plate,
            'vehicle_type' => $type,
            'check_in_at' => now()->subHour(),
            'status' => 'ACTIVE',
        ]);
    }

    public function test_lists_areas_sorted_by_name(): void
    {
        $this->makeArea('Gedung B');
        $this->makeArea('Gedung A');

        $this->getJson('/api/areas')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Gedung A');
    }

    public function test_creates_area(): void
    {
        $this->postJson('/api/areas', $this->payload())->assertCreated();

        $this->assertDatabaseHas('parking_areas', [
            'name' => 'Gedung A',
            'capacity_motor' => 50,
            'capacity_mobil' => 20,
        ]);
    }

    public function test_rejects_duplicate_name(): void
    {
        $this->makeArea('Gedung A');

        $this->postJson('/api/areas', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[DataProvider('invalidPayloads')]
    public function test_validates_input(array $override, string $field): void
    {
        $this->postJson('/api/areas', $this->payload($override))
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field]);
    }

    public static function invalidPayloads(): array
    {
        return [
            'nama kosong'            => [['name' => ''], 'name'],
            'nama 256 karakter'      => [['name' => str_repeat('A', 256)], 'name'],
            'kapasitas motor -1'     => [['capacity_motor' => -1], 'capacity_motor'],
            'kapasitas mobil bukan angka' => [['capacity_mobil' => 'abc'], 'capacity_mobil'],
            'kapasitas motor 10001'  => [['capacity_motor' => 10001], 'capacity_motor'],
        ];
    }

    public function test_accepts_boundary_values(): void
    {
        $this->postJson('/api/areas', $this->payload([
            'name' => str_repeat('A', 255),
            'capacity_motor' => 0,
            'capacity_mobil' => 10000,
        ]))->assertCreated();
    }

    public function test_shows_area(): void
    {
        $area = $this->makeArea();

        $this->getJson("/api/areas/{$area->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Gedung A');
    }

    public function test_show_returns_404_for_unknown_area(): void
    {
        $this->getJson('/api/areas/9999')->assertNotFound();
    }

    public function test_updates_area(): void
    {
        $area = $this->makeArea();

        $this->putJson("/api/areas/{$area->id}", $this->payload([
            'name' => 'Gedung Baru',
            'capacity_motor' => 80,
        ]))->assertOk();

        $this->assertDatabaseHas('parking_areas', [
            'id' => $area->id,
            'name' => 'Gedung Baru',
            'capacity_motor' => 80,
        ]);
    }

    public function test_update_allows_keeping_same_name(): void
    {
        $area = $this->makeArea('Gedung A');

        $this->putJson("/api/areas/{$area->id}", $this->payload(['capacity_motor' => 99]))
            ->assertOk();
    }

    public function test_update_rejects_name_used_by_other_area(): void
    {
        $this->makeArea('Gedung A');
        $other = $this->makeArea('Gedung B');

        $this->putJson("/api/areas/{$other->id}", $this->payload(['name' => 'Gedung A']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_rejects_capacity_below_active_vehicles(): void
    {
        $area = $this->makeArea(motor: 2);
        $this->addActiveRecord($area, 'B 1111 AAA');
        $this->addActiveRecord($area, 'B 2222 BBB');

        $this->putJson("/api/areas/{$area->id}", $this->payload(['capacity_motor' => 1]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['capacity_motor']);

        $this->putJson("/api/areas/{$area->id}", $this->payload(['capacity_motor' => 2]))
            ->assertOk();
    }

    public function test_deletes_area_without_history(): void
    {
        $area = $this->makeArea();

        $this->deleteJson("/api/areas/{$area->id}")->assertOk();

        $this->assertDatabaseMissing('parking_areas', ['id' => $area->id]);
    }

    public function test_rejects_delete_for_area_with_history(): void
    {
        $area = $this->makeArea();
        $this->addActiveRecord($area, 'B 1111 AAA');

        $this->deleteJson("/api/areas/{$area->id}")->assertStatus(409);

        $this->assertDatabaseHas('parking_areas', ['id' => $area->id]);
    }
}
