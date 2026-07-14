<?php

namespace Tests\Unit\Support;

use App\Support\AdmissionNumberSort;
use PHPUnit\Framework\TestCase;

class AdmissionNumberSortTest extends TestCase
{
    public function test_compare_sorts_numeric_and_alphanumeric_values_naturally(): void
    {
        $values = ['ADM-10', '2', '10', 'ADM-2', '1'];
        usort($values, AdmissionNumberSort::compare(...));

        $this->assertSame(['1', '2', '10', 'ADM-2', 'ADM-10'], $values);
    }

    public function test_sort_rows_orders_by_admission_number_field(): void
    {
        $rows = [
            ['admission_number' => '200'],
            ['admission_number' => '20'],
            ['adm_or_staff_no' => '5'],
        ];

        $sorted = AdmissionNumberSort::sortRows($rows);

        $this->assertSame('5', $sorted[0]['adm_or_staff_no']);
        $this->assertSame('20', $sorted[1]['admission_number']);
        $this->assertSame('200', $sorted[2]['admission_number']);
    }
}
