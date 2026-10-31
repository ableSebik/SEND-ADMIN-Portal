<?php

namespace App\Services;

class VehicleFields
{
    public static function rules(): array
    {
        return ['name' => 'required|string|max:180', 'registration' => 'required|string|max:40', 'location' => ['required', \Illuminate\Validation\Rule::in(\App\Models\User::OFFICES)], 'serial' => 'nullable|string|max:100', 'driver_id' => 'nullable|integer|exists:users,id', 'odometer' => 'sometimes|required|numeric|min:0|max:999999999.9', 'notes' => 'nullable|string|max:3000'];
    }
}

