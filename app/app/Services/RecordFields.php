<?php

namespace App\Services;

class RecordFields
{
    public static function fields(string $module): array
    {
        $shared = ['purchase_date' => ['Acquisition date', 'date'], 'warranty_expiry' => ['Warranty expiry', 'date'], 'manufacturer' => ['Manufacturer / make', 'text'], 'model' => ['Model', 'text']];

        return match ($module) {
            'vendors' => ['trading_name' => ['Trading name', 'text'], 'business_number' => ['Business registration number', 'text'], 'tin' => ['Tax identification number', 'text'], 'vat_status' => ['VAT registration status', 'text'], 'business_address' => ['Business address', 'text'], 'region' => ['Region', 'text'], 'country' => ['Country', 'text'], 'website' => ['Website', 'url'], 'contact_person' => ['Primary contact person', 'text'], 'contact_title' => ['Contact designation', 'text'], 'alternative_contact' => ['Alternative contact details', 'text'], 'payment_terms' => ['Payment terms', 'text']],
            'vehicles' => [...$shared, 'manufacture_year' => ['Year of manufacture', 'number'], 'fuel_type' => ['Fuel type', 'text'], 'engine_number' => ['Engine number', 'text'], 'odometer' => ['Current odometer (km)', 'number'], 'insurance_expiry' => ['Insurance expiry', 'date'], 'roadworthy_expiry' => ['Roadworthiness expiry', 'date'], 'next_service' => ['Next service date', 'date'], 'condition' => ['Vehicle condition', 'text']],
            'assets' => [...$shared, 'condition' => ['Asset condition', 'text'], 'purchase_order' => ['Purchase order reference', 'text'], 'invoice' => ['Invoice reference', 'text'], 'supplier' => ['Supplier / vendor reference', 'text'], 'funding_source' => ['Funding source', 'text'], 'project' => ['Project / Department', 'text'], 'physical_location' => ['Physical location', 'text'], 'ownership' => ['Ownership status', 'text'], 'hostname' => ['Hostname', 'text'], 'operating_system' => ['Operating system', 'text'], 'processor' => ['Processor', 'text'], 'ram' => ['RAM', 'text'], 'storage' => ['Storage capacity', 'text'], 'security_status' => ['Endpoint security status', 'text']],
            'trips' => ['requesting_staff' => ['Requesting staff member', 'text'], 'passengers' => ['Number of passengers', 'number']],default => []
        };
    }

    public static function rules(string $module): array
    {
        $rules = [];
        foreach (self::fields($module) as $key => [$label,$type]) {
            $rules[$key] = match ($type) {
                'date' => 'nullable|date','url' => 'nullable|url|max:250','number' => 'nullable|numeric|min:0',default => 'nullable|string|max:250'
            };
        }

        return $rules;
    }
}
