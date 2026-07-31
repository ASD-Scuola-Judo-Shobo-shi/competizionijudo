<?php

declare(strict_types=1);

namespace App\Service;

final class SpreadsheetTable
{
    /**
     * @param list<array{number: int, values: list<string>}> $rows
     */
    public function __construct(
        public readonly array $rows,
        public readonly bool $excelDate1904 = false
    ) {
    }
}
