<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $plate = $this->input('plate_number');

        if (is_string($plate)) {
            $this->merge([
                'plate_number' => strtoupper(preg_replace('/\s+/', ' ', trim($plate))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'plate_number' => ['required', 'string', 'max:20'],
            'vehicle_type' => ['required', 'in:motor,mobil'],
            'parking_area_id' => ['required', 'integer', 'exists:parking_areas,id'],
        ];
    }
}
