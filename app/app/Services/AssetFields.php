<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\Rule;

class AssetFields
{
    public const TYPES = ['motor_vehicle' => 'Motor Vehicle', 'office_equipment' => 'Office Equipment', 'furniture_fittings' => 'Furniture, Fixtures and Fittings'];

    public const FIELDS = ['asset_type', 'year', 'description', 'office_id', 'purchase_date', 'supplier', 'origin', 'model', 'manufacturer', 'manufacture_year', 'serial', 'staff_responsible', 'invoice', 'price', 'project', 'donor'];

    public static function type(?string $value): ?string
    {
        if (isset(self::TYPES[$value ?? ''])) {
            return $value;
        }
        foreach (self::TYPES as $key => $label) {
            if (strcasecmp(trim($value ?? ''), $label) === 0) {
                return $key;
            }
        }

        return match (strtolower(trim($value ?? ''))) {
            'ict','office equipment' => 'office_equipment','motor vehicle' => 'motor_vehicle','furniture fixtures and fittings' => 'furniture_fittings',default => null
        };
    }

    public static function rules(): array
    {
        return ['asset_type' => ['required', Rule::in(array_keys(self::TYPES))], 'name' => 'required|string|max:180', 'location' => ['required', Rule::in(User::OFFICES)], 'department' => ['nullable', Rule::in(User::DEPARTMENTS)], 'year' => 'required|integer|min:1900|max:2100', 'description' => 'nullable|string|max:3000', 'office_id' => 'nullable|string|max:100', 'purchase_date' => 'nullable|date_format:Y-m-d', 'supplier' => 'nullable|string|max:180', 'origin' => 'nullable|string|max:100', 'model' => 'nullable|string|max:180', 'manufacturer' => 'nullable|string|max:180', 'manufacture_year' => 'nullable|integer|min:1900|max:2100', 'serial' => 'nullable|string|max:180', 'staff_responsible' => 'nullable|string|max:180', 'invoice' => 'nullable|string|max:100', 'price' => 'required|numeric|min:0|max:9999999999.99', 'project' => 'nullable|string|max:180', 'donor' => 'nullable|string|max:180', 'notes' => 'nullable|string|max:3000'];
    }

    public static function prepare(array $data): array
    {
        $data['department'] = $data['department'] ?? '';
        $data['category'] = self::TYPES[$data['asset_type']];
        $data['cost'] = $data['price'];
        if ($data['asset_type'] !== 'motor_vehicle') {
            $data['origin'] = null;
        }

        return $data;
    }
}
