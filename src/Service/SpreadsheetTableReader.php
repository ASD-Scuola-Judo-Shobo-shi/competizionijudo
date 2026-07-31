<?php

declare(strict_types=1);

namespace App\Service;

use SimpleXMLElement;
use ZipArchive;

final class SpreadsheetTableReader
{
    private const MAX_ARCHIVE_ENTRIES = 1_000;
    private const MAX_UNCOMPRESSED_BYTES = 64 * 1024 * 1024;
    private const MAX_COLUMNS = 512;
    private const DELIMITER_SAMPLE_LINES = 25;

    public function read(string $path, int $maximumRows): SpreadsheetTable
    {
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new AthleteCsvImportException('club.area.csv.upload_failed');
        }

        try {
            $signature = fread($stream, 4);
        } finally {
            fclose($stream);
        }

        if (is_string($signature) && str_starts_with($signature, "PK\x03\x04")) {
            return $this->readXlsx($path, $maximumRows);
        }

        return $this->readDelimitedText($path, $maximumRows);
    }

    private function readDelimitedText(string $path, int $maximumRows): SpreadsheetTable
    {
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new AthleteCsvImportException('club.area.csv.upload_failed');
        }

        try {
            $sampleLines = [];
            while (
                count($sampleLines) < self::DELIMITER_SAMPLE_LINES
                && ($sampleLine = fgets($stream)) !== false
            ) {
                if (trim($sampleLine) !== '') {
                    $sampleLines[] = $sampleLine;
                }
            }
            if ($sampleLines === []) {
                return new SpreadsheetTable([]);
            }

            $delimiter = $this->delimiter($sampleLines);
            rewind($stream);
            $rows = [];
            $rowNumber = 0;

            while (($fields = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
                $rowNumber++;
                if (count($rows) >= $maximumRows) {
                    throw new AthleteCsvImportException('club.area.csv.too_many_rows');
                }

                $values = [];
                foreach (array_slice($fields, 0, self::MAX_COLUMNS) as $field) {
                    $values[] = $field ?? '';
                }
                $rows[] = ['number' => $rowNumber, 'values' => $values];
            }

            return new SpreadsheetTable($rows);
        } finally {
            fclose($stream);
        }
    }

    /** @param list<string> $sampleLines */
    private function delimiter(array $sampleLines): string
    {
        $bestDelimiter = ',';
        $bestCount = 0;

        foreach ([',', ';', "\t"] as $delimiter) {
            foreach ($sampleLines as $line) {
                $fields = str_getcsv(rtrim($line, "\r\n"), $delimiter, '"', '');
                if (count($fields) > $bestCount) {
                    $bestDelimiter = $delimiter;
                    $bestCount = count($fields);
                }
            }
        }

        return $bestDelimiter;
    }

    private function readXlsx(string $path, int $maximumRows): SpreadsheetTable
    {
        $archive = new ZipArchive();
        if ($archive->open($path) !== true) {
            throw new AthleteCsvImportException('club.area.csv.invalid_file');
        }

        try {
            $this->validateArchive($archive);
            $workbook = $this->xml($archive, 'xl/workbook.xml');
            $relationships = $this->xml($archive, 'xl/_rels/workbook.xml.rels');
            $sheetPath = $this->firstSheetPath($workbook, $relationships);
            $sheet = $this->xml($archive, $sheetPath);
            $sharedStrings = $this->sharedStrings($archive);
            $date1904 = strtolower((string) ($workbook->workbookPr['date1904'] ?? 'false')) === 'true'
                || (string) ($workbook->workbookPr['date1904'] ?? '0') === '1';
            $rows = [];

            $sheetRows = $sheet->xpath(
                '/*[local-name()="worksheet"]/*[local-name()="sheetData"]/*[local-name()="row"]'
            );
            foreach ($sheetRows ?: [] as $position => $sheetRow) {
                if (count($rows) >= $maximumRows) {
                    throw new AthleteCsvImportException('club.area.csv.too_many_rows');
                }

                $rowNumber = (int) ($sheetRow['r'] ?? ($position + 1));
                $valuesByColumn = [];
                $highestColumn = -1;
                $cells = $sheetRow->xpath('./*[local-name()="c"]');

                foreach ($cells ?: [] as $cell) {
                    $column = $this->columnIndex((string) ($cell['r'] ?? ''));
                    if ($column === null || $column >= self::MAX_COLUMNS) {
                        continue;
                    }

                    $valuesByColumn[$column] = $this->cellValue($cell, $sharedStrings);
                    $highestColumn = max($highestColumn, $column);
                }

                $values = $highestColumn >= 0 ? array_fill(0, $highestColumn + 1, '') : [];
                foreach ($valuesByColumn as $column => $value) {
                    $values[$column] = $value;
                }
                $rows[] = ['number' => $rowNumber, 'values' => $values];
            }

            return new SpreadsheetTable($rows, $date1904);
        } finally {
            $archive->close();
        }
    }

    private function validateArchive(ZipArchive $archive): void
    {
        if ($archive->numFiles <= 0 || $archive->numFiles > self::MAX_ARCHIVE_ENTRIES) {
            throw new AthleteCsvImportException('club.area.csv.invalid_file');
        }

        $uncompressedBytes = 0;
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $stat = $archive->statIndex($index);
            if (!is_array($stat)) {
                throw new AthleteCsvImportException('club.area.csv.invalid_file');
            }

            $uncompressedBytes += (int) $stat['size'];
            if ($uncompressedBytes > self::MAX_UNCOMPRESSED_BYTES) {
                throw new AthleteCsvImportException('club.area.csv.invalid_file');
            }
        }

        foreach (['[Content_Types].xml', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels'] as $name) {
            if ($archive->locateName($name) === false) {
                throw new AthleteCsvImportException('club.area.csv.invalid_file');
            }
        }
    }

    private function xml(ZipArchive $archive, string $name): SimpleXMLElement
    {
        $contents = $archive->getFromName($name);
        if (!is_string($contents)) {
            throw new AthleteCsvImportException('club.area.csv.invalid_file');
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (!$xml instanceof SimpleXMLElement) {
            throw new AthleteCsvImportException('club.area.csv.invalid_file');
        }

        return $xml;
    }

    private function firstSheetPath(SimpleXMLElement $workbook, SimpleXMLElement $relationships): string
    {
        $sheets = $workbook->xpath(
            '/*[local-name()="workbook"]/*[local-name()="sheets"]/*[local-name()="sheet"]'
        );
        if (empty($sheets)) {
            throw new AthleteCsvImportException('club.area.csv.invalid_file');
        }

        $relationshipId = '';
        $relationshipNamespaces = $sheets[0]->getNamespaces(true);
        if (isset($relationshipNamespaces['r'])) {
            $attributes = $sheets[0]->attributes($relationshipNamespaces['r']);
            $relationshipId = (string) ($attributes['id'] ?? '');
        }
        if ($relationshipId === '') {
            throw new AthleteCsvImportException('club.area.csv.invalid_file');
        }

        $relationshipNodes = $relationships->xpath('/*[local-name()="Relationships"]/*[local-name()="Relationship"]');
        foreach ($relationshipNodes ?: [] as $relationship) {
            if ((string) ($relationship['Id'] ?? '') !== $relationshipId) {
                continue;
            }

            $target = str_replace('\\', '/', (string) ($relationship['Target'] ?? ''));
            $target = ltrim($target, '/');
            if (str_starts_with($target, 'xl/')) {
                $path = $target;
            } else {
                $path = 'xl/' . $target;
            }

            if (str_contains($path, '../') || !str_starts_with($path, 'xl/')) {
                break;
            }

            return $path;
        }

        throw new AthleteCsvImportException('club.area.csv.invalid_file');
    }

    /** @return list<string> */
    private function sharedStrings(ZipArchive $archive): array
    {
        if ($archive->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $xml = $this->xml($archive, 'xl/sharedStrings.xml');
        $nodes = $xml->xpath('/*[local-name()="sst"]/*[local-name()="si"]');
        $strings = [];

        foreach ($nodes ?: [] as $node) {
            $textNodes = $node->xpath('.//*[local-name()="t"]');
            $value = '';
            foreach ($textNodes ?: [] as $textNode) {
                $value .= (string) $textNode;
            }
            $strings[] = $value;
        }

        return $strings;
    }

    /** @param list<string> $sharedStrings */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) ($cell['t'] ?? '');
        if ($type === 'inlineStr') {
            $textNodes = $cell->xpath('./*[local-name()="is"]//*[local-name()="t"]');
            $value = '';
            foreach ($textNodes ?: [] as $textNode) {
                $value .= (string) $textNode;
            }

            return $value;
        }

        $valueNodes = $cell->xpath('./*[local-name()="v"]');
        $raw = empty($valueNodes) ? '' : (string) $valueNodes[0];

        if ($type === 's' && ctype_digit($raw)) {
            return $sharedStrings[(int) $raw] ?? '';
        }

        if ($type === 'b') {
            return $raw === '1' ? 'TRUE' : 'FALSE';
        }

        return $raw;
    }

    private function columnIndex(string $reference): ?int
    {
        if (preg_match('/^([A-Z]+)\d+$/i', $reference, $matches) !== 1) {
            return null;
        }

        $index = 0;
        foreach (str_split(strtoupper($matches[1])) as $character) {
            $index = ($index * 26) + ord($character) - 64;
        }

        return $index - 1;
    }
}
