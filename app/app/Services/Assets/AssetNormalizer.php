<?php

namespace App\Services\Assets;

use DateTimeImmutable;

class AssetNormalizer
{
    public static function text(mixed $v): ?string
    {
        $v = trim(preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', (string) ($v ?? ''))));

        return $v === '' || in_array(strtoupper($v), ['N/A', 'NA', 'NIL', 'NONE', '-']) ? null : $v;
    }

    public static function key(mixed $v): string
    {
        return mb_strtolower(self::text($v) ?? '');
    }

    public static function serial(mixed $v): ?string
    {
        $v = self::text($v);

        return $v ? strtoupper(preg_replace('/\s+/u', '', $v)) : null;
    }

    public static function money(mixed $v): ?int
    {
        if (self::text($v) === null) {
            return null;
        }if (is_numeric($v)) {
            return (int) round((float) $v * 100);
        }$v = preg_replace('/(?:GHS|GH¢|GH�|GH₵|GH\?|[\s,])/ui', '', (string) $v);

        return preg_match('/^-?\d+(?:\.\d{1,8})?$/', $v) ? (int) round((float) $v * 100) : null;
    }

    public static function date(mixed $v, bool $date1904 = false): ?string
    {
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }if (self::text($v) === null) {
            return null;
        }if (is_numeric($v)) {
            if ((float) $v < 1 || (float) $v > 100000) {
                return null;
            }

return (new DateTimeImmutable($date1904 ? '1904-01-01' : '1899-12-30'))->modify('+'.(int) $v.' days')->format('Y-m-d');
        }$v = trim((string) $v, " '\t\n\r");
        $formats = preg_match('/^[0-9]{4}-/', $v) ? ['!Y-m-d', '!Y-m-d H:i:s'] : (preg_match('/[0-9]{4}$/', $v) ? ['!j-n-Y', '!j/n/Y', '!j.n.Y'] : ['!j-n-y', '!j/n/y']);
        foreach ($formats as $format) {
            $d = DateTimeImmutable::createFromFormat($format, $v);
            $errors = DateTimeImmutable::getLastErrors();
            if ($d && (! $errors || (! $errors['warning_count'] && ! $errors['error_count']))) {
                return $d->format('Y-m-d');
            }
        }

return null;
    }

    public static function classify(array $v): string
    {
        $filled = array_filter(array_map([self::class, 'text'], $v), fn ($x) => $x !== null);
        if (! $filled) {
            return 'BLANK';
        }$all = implode(' ', $filled);
        if (preg_match('/\b(?:bal\s*[bc]\s*\/?[fd]|sub[ -]?total|net assets|total)\b/i', $all)) {
            return 'SUMMARY';
        }
        if (preg_match('/^Office Furniture$/i', $all)) {
            return 'CATEGORY_HEADER';
        }
        if (preg_match('/Name of Asset|Date of Purchase|Fixed Assets Register|Office ID No|Year of Manuf/i', $all)) {
            return 'COLUMN_HEADER';
        }
        if (! self::text($v[1] ?? null) && ! self::text($v[2] ?? null)) {
            $header = array_filter(array_slice($v, 0, 11), fn ($x) => self::text($x) !== null);
            if (! $header) {
                return 'SUMMARY';
            }
            if (count($filled) <= 3) {
                return preg_match('/^(?:19|20)\d{2}$/', $all) ? 'HEADER_YEAR' : 'HEADER_PROJECT';
            }
        }
        if (count($filled) <= 2 && preg_match('/^(?:19|20)\d{2}$/', self::text($v[0] ?? null) ?? '')) {
            return 'HEADER_YEAR';
        }

        return self::text($v[1] ?? null) ? 'ASSET' : 'UNKNOWN';
    }

    public static function tag(?string $tag): ?array
    {
        if (! $tag || ! preg_match('~^SG/(AO|TO|TDO|SO)/([A-Z]+)/([1-9][0-9]*|0+[1-9][0-9]*)/([0-9]{2})$~', strtoupper($tag), $m)) {
            return null;
        }

return ['office' => $m[1], 'category' => $m[2], 'sequence' => (int) $m[3], 'year' => 2000 + (int) $m[4]];
    }
}
