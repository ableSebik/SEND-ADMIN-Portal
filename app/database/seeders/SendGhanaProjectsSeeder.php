<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SendGhanaProjectsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $donors = [];
            foreach (['Global Affairs Canada', 'Oxfam', 'Netherlands Ministry of Foreign Affairs', 'DANIDA', 'DKA Austria', 'Mastercard Foundation'] as $name) {
                $key = mb_strtolower($name);
                DB::table('asset_donors')->insertOrIgnore(['name' => $name, 'normalized_name' => $key,
                    'type' => $name === 'Mastercard Foundation' ? 'Programme partner — donor link needs review' : 'Donor',
                    'created_at' => now(), 'updated_at' => now()]);
                $donors[$name] = DB::table('asset_donors')->where('normalized_name', $key)->value('id');
            }
            $base = 'https://sendwestafrica.org/ghana/';
            $rows = [
                ['WEACT', 'Women’s Economic Advancement for Collective Transformation (WEACT)', 'Global Affairs Canada', 'Oxfam Québec', 'Completed', 'Donor record identifies the project as closed, ending February 2025.', 'https://w05.international.gc.ca/projectbrowser-banqueprojets/project-projet/details/P006760001'],
                ['P2C', 'Power to Choose (P2C)', 'Global Affairs Canada', 'Oxfam in Ghana', 'Active', 'Public programme period: 2021–2028. Exact SEND grant dates require internal confirmation.', 'https://sendwestafrica.org/nu/gh/wp-content/uploads/sites/2/2024/05/BREAKING-MENSTRUAL-SILENCE-SENDE-GHANA-May-2024.pdf'],
                ['FAIR-ALL', 'FAIR for ALL / Power of Voices Partnership', 'Netherlands Ministry of Foreign Affairs', 'Oxfam Ghana', 'Completed', 'Published project period: January 2021–December 2025.', $base.'projects/pvp-fair-for-all/'],
                ['SPI', 'Strategic Partnership Initiative for Ghana and West Africa', 'DANIDA', 'Oxfam in Ghana', 'Completed', 'Published project period: 2022–2025.', $base.'projects/strategic-partnership-initiative-for-ghana-and-west-africa/'],
                ['CHILD-LABOUR', 'Evidence Informed Advocacy for a Child Labour-Free Sector', 'DKA Austria', null, 'Active', 'Published project period: 2024–2026. Exact grant dates require internal confirmation.', $base.'projects/child-labour-dka/'],
                ['GROW', 'GROW', 'Oxfam', null, 'Needs review', 'Oxfam funding documented in the 2022 annual report. Current grant status requires confirmation.', $base.'download/19584/?tmstv=1753890639'],
                ['FILMA', 'Financial Inclusion for Last Mile Actors (FILMA)', null, 'Mastercard Foundation; Temple Investments', 'Active', 'Published programme period: 2024–2028. The project page identifies Mastercard Foundation and Temple Investments as partners; confirm the direct contracting/funding relationship internally.', $base.'projects/filma/'],
                ['U-CARE', 'United for Care (U-CARE)', null, null, 'Needs review', 'Listed in SEND’s project directory. Donor, exact dates and current grant status require confirmation.', $base.'projects/'],
                ['CLM-FCC', 'Community Led Monitoring (CLM) & FCC', null, null, 'Needs review', 'Listed in SEND’s project directory. Donor, exact dates and current grant status require confirmation.', $base.'projects/'],
                ['COCOA-MAP', 'Cocoa Advocacy: Multi-Actor Partnership', null, null, 'Needs review', 'Listed in SEND’s project directory. Donor, exact dates and current grant status require confirmation.', $base.'projects/'],
                ['FOPWL', 'Advocacy for Mandatory Front-of-Pack Food Warning Labelling (FOPWL)', null, null, 'Needs review', 'Listed in SEND’s project directory. Donor, exact dates and current grant status require confirmation.', $base.'projects/'],
            ];
            foreach ($rows as [$code, $name, $donor, $partner, $status, $notes, $source]) {
                // Add missing records only; reruns never overwrite user-maintained information.
                if (DB::table('asset_projects')->where('code', $code)->orWhere('name', $name)->exists()) {
                    continue;
                }
                DB::table('asset_projects')->insert(['code' => $code, 'name' => $name, 'donor_id' => $donor ? $donors[$donor] : null,
                    'funding_partner' => $partner, 'status' => $status, 'notes' => $notes.' Code is a portal reference, not a verified grant number. Public sources reviewed 31 October 2026.',
                    'source_url' => $source, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
        $this->command?->info('SEND Ghana reference data added: 11 projects and 6 donor organisations (existing entries retained).');
    }
}
