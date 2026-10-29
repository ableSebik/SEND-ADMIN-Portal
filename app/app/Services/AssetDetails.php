<?php

namespace App\Services;

use App\Models\AdminRecord;
use Illuminate\Support\Facades\DB;

class AssetDetails
{
    public static function sync(AdminRecord $record): void
    {
        if ($record->module !== 'assets') {
            return;
        }
        $data = $record->data ?? [];
        $values = [];
        foreach (AssetFields::FIELDS as $field) {
            $values[$field] = $data[$field] ?? null;
        }
        $values['asset_type'] = AssetFields::type($data['asset_type'] ?? $record->category);
        $values['price'] = $data['price'] ?? $data['cost'] ?? null;
        $values['year'] = $data['year'] ?? (! empty($data['purchase_date']) ? (int) substr($data['purchase_date'], 0, 4) : null);
        $values['office_id'] = $data['office_id'] ?? $data['registration'] ?? $data['asset_tag'] ?? null;
        $values['created_at'] = $record->created_at;
        $values['updated_at'] = $record->updated_at;
        DB::table('asset_details')->updateOrInsert(['record_id' => $record->id], $values);
    }
}
