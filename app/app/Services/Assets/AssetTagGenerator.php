<?php

namespace App\Services\Assets;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AssetTagGenerator
{
    public function next(string $office, string $category, int $year): string
    {
        if (! in_array($office, ['AO', 'TO', 'TDO', 'SO'], true) || ! preg_match('/^[A-Z]{1,12}$/', $category) || $year < 2000 || $year > 2099) {
            throw new InvalidArgumentException('Invalid office, category code or tag year.');
        }

        return DB::transaction(function () use ($office, $category, $year) {
            DB::table('asset_tag_counters')->insertOrIgnore(['office' => $office, 'category' => $category, 'year' => $year, 'sequence' => 0]);
            $counter = DB::table('asset_tag_counters')->where(compact('office', 'category', 'year'))->lockForUpdate()->first();
            $max = $counter->sequence;
            $yy = substr((string) $year, -2);
            foreach (DB::table('assets')->where('asset_tag', 'like', 'SG/'.$office.'/'.$category.'/%/'.$yy)->pluck('asset_tag') as $tag) {
                $parsed = AssetNormalizer::tag($tag);
                if ($parsed) {
                    $max = max($max, $parsed['sequence']);
                }
            }$next = $max + 1;
            DB::table('asset_tag_counters')->where('id', $counter->id)->update(['sequence' => $next]);

            return 'SG/'.$office.'/'.$category.'/'.$next.'/'.$yy;
        });
    }
}
