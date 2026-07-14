<?php

namespace App\Support;

class AdmissionNumberSort
{
    public static function compare(?string $left, ?string $right): int
    {
        $left = trim((string) $left);
        $right = trim((string) $right);

        return strnatcasecmp($left, $right);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function sortRows(array $rows): array
    {
        usort($rows, static function (array $left, array $right): int {
            $leftNumber = $left['admission_number'] ?? $left['adm_or_staff_no'] ?? '';
            $rightNumber = $right['admission_number'] ?? $right['adm_or_staff_no'] ?? '';

            return self::compare((string) $leftNumber, (string) $rightNumber);
        });

        return $rows;
    }
}
