<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ParkingAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('parking_areas', 'name')->ignore($this->route('area')),
            ],
            'capacity_motor' => ['required', 'integer', 'min:0', 'max:10000'],
            'capacity_mobil' => ['required', 'integer', 'min:0', 'max:10000'],
        ];
    }
}
