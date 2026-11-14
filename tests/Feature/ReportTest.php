<?php

namespace Tests\Feature;

use App\Models\ParkingArea;
use App\Models\ParkingRecord;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeArea(string $name = 'Gedung A', int $motor = 2, int $mobil = 1): ParkingArea
    {
        return ParkingArea::create([
            'name' => $name,
            'capacity_motor' => $motor,
            'capacity_mobil' => $mobil,
        ]);
    }

    private function addActiveRecord(ParkingArea $area, string $plate, string $type): ParkingRecord
    {
        return ParkingRecord::create([
            'parking_area_id' => $area->id,
            'plate_number' => $plate,
            'vehicle_type' => $type,
            'check_in_at' => now()->subHour(),
            'status' => 'ACTIVE',
        ]);
    }

    private function addDoneRecord(
        ParkingArea $area,
        string $plate,
        string $type,
        CarbonInterface $checkOutAt,
        int $fee
    ): ParkingRecord {
        return ParkingRecord::create([
            'parking_area_id' => $area->id,
            'plate_number' => $plate,
            'vehicle_type' => $type,
            'check_in_at' => $checkOutAt->copy()->subHour(),
            'check_out_at' => $checkOutAt,
            'fee' => $fee,
            'status' => 'DONE',
        ]);
    }

    public function test_occupancy_counts_only_active_records_per_area_and_type(): void
    {
        $a = $this->makeArea('Gedung A', motor: 3, mobil: 2);
        $this->makeArea('Gedung B', motor: 5, mobil: 4);

        $this->addActiveRecord($a, 'B 1111 AAA', 'motor');
        $this->addActiveRecord($a, 'B 2222 BBB', 'motor');
        $this->addActiveRecord($a, 'B 3333 CCC', 'mobil');
        $this->addDoneRecord($a, 'B 4444 DDD', 'motor', now()->subHour(), 2000);

        $this->getJson('/api/reports/occupancy')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Gedung A')
            ->assertJsonPath('data.0.motor.capacity', 3)
            ->assertJsonPath('data.0.motor.occupied', 2)
            ->assertJsonPath('data.0.motor.available', 1)
            ->assertJsonPath('data.0.mobil.occupied', 1)
            ->assertJsonPath('data.0.mobil.available', 1)
            ->assertJsonPath('data.1.name', 'Gedung B')
            ->assertJsonPath('data.1.motor.occupied', 0)
            ->assertJsonPath('data.1.motor.available', 5);
    }

    public function test_occupancy_drops_after_check_out(): void
    {
        $area = $this->makeArea(motor: 2);

        $this->postJson('/api/check-in', [
            'plate_number' => 'B 1111 AAA',
            'vehicle_type' => 'motor',
            'parking_area_id' => $area->id,
        ])->assertCreated();

        $this->getJson('/api/reports/occupancy')->assertJsonPath('data.0.motor.occupied', 1);

        $this->postJson('/api/check-out', ['plate_number' => 'B 1111 AAA'])->assertOk();

        $this->getJson('/api/reports/occupancy')->assertJsonPath('data.0.motor.occupied', 0);
    }

    public function test_revenue_sums_done_records_for_the_requested_day_only(): void
    {
        $area = $this->makeArea();
        $this->travelTo(Carbon::parse('2026-11-14 12:00:00'));

        $this->addDoneRecord($area, 'B 1111 AAA', 'motor', Carbon::parse('2026-11-14 08:00:00'), 2000);
        $this->addDoneRecord($area, 'B 2222 BBB', 'motor', Carbon::parse('2026-11-14 23:59:59'), 3000);
        $this->addDoneRecord($area, 'B 3333 CCC', 'mobil', Carbon::parse('2026-11-14 10:00:00'), 8000);
        // di luar tanggal: sehari sebelumnya dan sehari sesudahnya
        $this->addDoneRecord($area, 'B 4444 DDD', 'motor', Carbon::parse('2026-11-13 23:59:59'), 5000);
        $this->addDoneRecord($area, 'B 5555 EEE', 'mobil', Carbon::parse('2026-11-15 00:00:00'), 7000);
        // masih parkir: belum menghasilkan pendapatan
        $this->addActiveRecord($area, 'B 6666 FFF', 'motor');

        $this->getJson('/api/reports/revenue?date=2026-11-14')
            ->assertOk()
            ->assertJsonPath('data.date', '2026-11-14')
            ->assertJsonPath('data.total_transactions', 3)
            ->assertJsonPath('data.total_revenue', 13000)
            ->assertJsonPath('data.by_vehicle_type.motor.transactions', 2)
            ->assertJsonPath('data.by_vehicle_type.motor.revenue', 5000)
            ->assertJsonPath('data.by_vehicle_type.mobil.transactions', 1)
            ->assertJsonPath('data.by_vehicle_type.mobil.revenue', 8000);
    }

    public function test_revenue_defaults_to_today(): void
    {
        $area = $this->makeArea();
        $this->travelTo(Carbon::parse('2026-11-14 12:00:00'));
        $this->addDoneRecord($area, 'B 1111 AAA', 'motor', Carbon::parse('2026-11-14 09:00:00'), 2000);

        $this->getJson('/api/reports/revenue')
            ->assertOk()
            ->assertJsonPath('data.date', '2026-11-14')
            ->assertJsonPath('data.total_revenue', 2000);
    }

    public function test_revenue_is_zero_when_no_transactions(): void
    {
        $this->getJson('/api/reports/revenue?date=2026-01-01')
            ->assertOk()
            ->assertJsonPath('data.total_transactions', 0)
            ->assertJsonPath('data.total_revenue', 0)
            ->assertJsonPath('data.by_vehicle_type.motor.revenue', 0)
            ->assertJsonPath('data.by_vehicle_type.mobil.revenue', 0);
    }

    public function test_revenue_rejects_invalid_date(): void
    {
        $this->getJson('/api/reports/revenue?date=bukan-tanggal')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date']);

        $this->getJson('/api/reports/revenue?date=2026-02-30')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }
}
