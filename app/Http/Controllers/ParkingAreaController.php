<?php

namespace App\Http\Controllers;

use App\Http\Requests\ParkingAreaRequest;
use App\Models\ParkingArea;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ParkingAreaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => ParkingArea::orderBy('name')->get()]);
    }

    public function store(ParkingAreaRequest $request): JsonResponse
    {
        $area = ParkingArea::create($request->validated());

        return response()->json(['message' => 'Area parkir dibuat.', 'data' => $area], 201);
    }

    public function show(ParkingArea $area): JsonResponse
    {
        return response()->json(['data' => $area]);
    }

    public function update(ParkingAreaRequest $request, ParkingArea $area): JsonResponse
    {
        $data = $request->validated();

        foreach (['motor', 'mobil'] as $type) {
            $occupied = $area->records()
                ->where('vehicle_type', $type)
                ->where('status', 'ACTIVE')
                ->count();

            if ($data["capacity_{$type}"] < $occupied) {
                throw ValidationException::withMessages([
                    "capacity_{$type}" => "Kapasitas {$type} tidak boleh lebih kecil dari {$occupied} kendaraan yang sedang parkir.",
                ]);
            }
        }

        $area->update($data);

        return response()->json(['message' => 'Area parkir diperbarui.', 'data' => $area]);
    }

    public function destroy(ParkingArea $area): JsonResponse
    {
        if ($area->records()->exists()) {
            return response()->json([
                'message' => 'Area yang sudah punya riwayat parkir tidak bisa dihapus. Set kapasitas ke 0 sebagai gantinya.',
            ], 409);
        }

        $area->delete();

        return response()->json(['message' => 'Area parkir dihapus.']);
    }
}
