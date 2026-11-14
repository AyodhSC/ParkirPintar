<?php

namespace App\Http\Controllers;

use App\Models\ParkingArea;
use App\Models\ParkingRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function occupancy(): JsonResponse
    {
        $counts = ParkingRecord::where('status', 'ACTIVE')
            ->selectRaw('parking_area_id, vehicle_type, COUNT(*) as total')
            ->groupBy('parking_area_id', 'vehicle_type')
            ->get();

        $data = ParkingArea::orderBy('name')->get()->map(function ($area) use ($counts) {
            $occupied = fn (string $type): int => (int) ($counts
                ->where('parking_area_id', $area->id)
                ->where('vehicle_type', $type)
                ->first()?->total ?? 0);

            $motor = $occupied('motor');
            $mobil = $occupied('mobil');

            return [
                'area_id' => $area->id,
                'name' => $area->name,
                'motor' => [
                    'capacity' => $area->capacity_motor,
                    'occupied' => $motor,
                    'available' => max(0, $area->capacity_motor - $motor),
                ],
                'mobil' => [
                    'capacity' => $area->capacity_mobil,
                    'occupied' => $mobil,
                    'available' => max(0, $area->capacity_mobil - $mobil),
                ],
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function revenue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $day = isset($validated['date'])
            ? Carbon::createFromFormat('Y-m-d', $validated['date'])
            : now();

        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

        $rows = ParkingRecord::where('status', 'DONE')
            ->whereBetween('check_out_at', [$start, $end])
            ->selectRaw('vehicle_type, COUNT(*) as transactions, COALESCE(SUM(fee), 0) as revenue')
            ->groupBy('vehicle_type')
            ->get()
            ->keyBy('vehicle_type');

        $byType = [];
        foreach (['motor', 'mobil'] as $type) {
            $row = $rows->get($type);
            $byType[$type] = [
                'transactions' => (int) ($row?->transactions ?? 0),
                'revenue' => (int) ($row?->revenue ?? 0),
            ];
        }

        return response()->json([
            'data' => [
                'date' => $start->toDateString(),
                'total_transactions' => array_sum(array_column($byType, 'transactions')),
                'total_revenue' => array_sum(array_column($byType, 'revenue')),
                'by_vehicle_type' => $byType,
            ],
        ]);
    }
}
