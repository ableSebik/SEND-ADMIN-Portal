<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $insert = function ($table, $data) use ($now) {
            DB::table($table)->insertOrIgnore([...$data, 'created_at' => $now, 'updated_at' => $now]);
        };
        foreach (['ICT' => 'Office Equipment (ICT)', 'MOTOR' => 'Motor Vehicles & Motorbikes', 'FURNITURE' => 'Furniture, Fixtures & Fittings'] as $code => $name) {
            $insert('asset_categories', ['code' => $code, 'name' => $name]);
        }
        $categories = ['LP' => ['Laptops', 'ICT'], 'DESKTOP' => ['Desktops/System Units', 'ICT'], 'TLT' => ['Tablets', 'ICT'], 'PH' => ['Smartphones', 'ICT'], 'PRT' => ['Printers/Scanners/Copiers', 'ICT'], 'UPS' => ['UPS & Power', 'ICT'], 'PJ' => ['Projectors & AV', 'ICT'], 'CAM' => ['Cameras & Media', 'ICT'], 'NET' => ['Networking', 'ICT'], 'CC' => ['Chairs', 'FURNITURE'], 'TBL' => ['Tables/Desks', 'FURNITURE'], 'CB' => ['Cabinets/Storage', 'FURNITURE'], 'AC' => ['Air Conditioners', 'FURNITURE'], 'APPLIANCE' => ['Appliances', 'FURNITURE'], 'BIKE' => ['Motorbikes', 'MOTOR'], 'VEH' => ['Vehicles', 'MOTOR']];
        foreach ($categories as $code => [$name,$parent]) {
            $insert('asset_categories', ['code' => $code, 'name' => $name, 'parent_id' => DB::table('asset_categories')->where('code', $parent)->value('id'), 'tag_code' => $code]);
        }
        $mappings = ['name' => ['Laptop' => ['Laptop', 'Laptops'], 'Tablet' => ['Tablet', 'Tablets'], 'Chair' => ['Chair', 'Chairs'], 'Air Conditioner' => ['Air conditioner', 'Airconditioner', 'Air Condition'], 'Refrigerator' => ['Refridgerator', 'Refrigerator'], 'Samsung' => ['Sumsung', 'Samsung'], 'Curtains' => ['Curtins', 'Curtains'], 'Motorbike' => ['Apsonic motorbike', 'apsonic motorbike', 'Motorbike']], 'supplier' => ['TradeMart Ghana Ltd' => ['TradeMart', 'TradeMart Ghana Ltd'], 'Compu Ghana' => ['Compu Ghana', 'COMPU-GHANA'], 'Next Computers' => ['Next Computers'], 'Anchor Stationeries' => ['Anchor Stationery', 'Anchor Stationeries'], 'Kingdom Books & Stationery' => ['Kingdom Books and Stationary', 'Kingdom Books and Stationery', 'Kingdom Books & Stationary', 'Kingdom Books & Stationery']], 'location' => [], 'tag_category' => []];
        foreach (['Accra', 'Tamale', 'Takoradi', 'Salaga'] as $office) {
            $canonical = 'SEND '.$office.' Office';
            $mappings['location'][$canonical] = [$office, 'SEND '.$office.' Office', $office.' office', 'Send '.$office];
            $insert('asset_locations', ['name' => $canonical, 'normalized_name' => strtolower($canonical), 'type' => 'OFFICE']);
        }
        foreach (['GAND', 'Penplusbytes', 'Tamale-Songtaba', 'Tamale-Youth Alive', 'Tamale-Swida', 'Bimbilla/Wulensi', 'Yendi/Zabzugu', 'Sogakope', 'Jasikan', 'Kajebi', 'Kpandai', 'Techiman', 'Atebubu', 'Kintampo'] as $site) {
            $mappings['location'][$site] = [$site];
            $insert('asset_locations', ['name' => $site, 'normalized_name' => strtolower($site), 'type' => in_array($site, ['GAND', 'Penplusbytes']) ? 'PARTNER' : 'FIELD']);
        }
        $codes = ['LP' => ['LP'], 'DESKTOP' => ['MTR', 'SU'], 'TLT' => ['TLT', 'TBT'], 'PH' => ['PH'], 'PRT' => ['PRT', 'PR', 'SCN', 'SCAN'], 'UPS' => ['UPS'], 'PJ' => ['PJ', 'PRO'], 'CAM' => ['CL', 'CAM'], 'CC' => ['CC', 'SWCH'], 'TBL' => ['TBL'], 'CB' => ['CB'], 'AC' => ['AC'], 'APPLIANCE' => ['FG', 'DPS', 'MVE']];
        foreach ($codes as $category => $aliases) {
            $mappings['tag_category'][$category] = $aliases;
        }
        foreach ($mappings as $field => $canonicalValues) {
            foreach ($canonicalValues as $canonical => $aliases) {
                foreach ($aliases as $alias) {
                    $insert('asset_lookup_mappings', ['field' => $field, 'alias' => mb_strtolower(trim($alias)), 'canonical' => $canonical]);
                }
            }
        }
        foreach (['USAID', 'Save the Children UK', 'INKOTA', 'DKA', 'Oxfam', 'Core (Admin)'] as $donor) {
            $insert('asset_donors', ['name' => $donor, 'normalized_name' => strtolower($donor), 'type' => $donor === 'Core (Admin)' ? 'INTERNAL' : 'DONOR']);
        }
        foreach (['FILMA', 'U-CARE', 'Women in Governance (U-CARE)', 'FOPWL (GAND)', 'FOPWL (SEND GHANA)', 'Sustainable Cocoa-GCCP', 'GHAI-Epidemics', 'GEOP (SC UK)', 'USAID CLM', 'INKOTA', 'DKA', 'HEAL', 'SRHR', 'ARHR', '7-1-7', 'ADMIN'] as $project) {
            $insert('asset_projects', ['code' => strtoupper($project), 'name' => $project, 'donor_id' => null]);
        }
        $insert('asset_settings', ['key' => 'depreciation', 'value' => json_encode(['enabled' => false, 'method' => null, 'useful_life_years' => null, 'residual_value' => null])]);
        $insert('asset_settings', ['key' => 'import_cost_policy', 'value' => json_encode(['cost_is_batch_total' => false, 'cost' => 'row-specific, never distributed', 'price_only' => 'provisional and flagged'])]);
    }
}
