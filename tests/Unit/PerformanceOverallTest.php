<?php

namespace Tests\Unit;

use App\Services\StudentPerformanceService;
use PHPUnit\Framework\TestCase;

/** Общая успеваемость в центре колец — среднее по показателям, где есть данные. */
class PerformanceOverallTest extends TestCase
{
    public function test_average_skips_metrics_without_data(): void
    {
        $this->assertSame(85, StudentPerformanceService::overall([
            ['value' => 100, 'empty' => false],
            ['value' => 67, 'empty' => false],
            ['value' => 88, 'empty' => false],
        ]));
        // Оценок ещё нет — их 0% не тянет среднее вниз
        $this->assertSame(84, StudentPerformanceService::overall([
            ['value' => 100, 'empty' => false],
            ['value' => 67, 'empty' => false],
            ['value' => 0, 'empty' => true],
        ]));
        $this->assertNull(StudentPerformanceService::overall([['value' => 0, 'empty' => true]]));
    }
}
