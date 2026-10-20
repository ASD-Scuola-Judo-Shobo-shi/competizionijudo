<?php

declare(strict_types=1);

namespace App\Service;

/** Shared upload validation and import reports for club and administrator workflows. */
final class AthleteCsvUpload
{
    private const REPORT_LIMIT = 200;

    /**
     * @param array<string, mixed>|null $file
     * @return array{
     *     type: string,
     *     message: string,
     *     report: list<array{row: int, identity: string, message: string, existing_athlete_id: int|null}>,
     *     omitted: int
     * }
     */
    public function import(?array $file, int $clubId, bool $mergeIncomplete = false): array
    {
        try {
            $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadError === UPLOAD_ERR_NO_FILE) {
                throw new AthleteCsvImportException('club.area.csv.file_required');
            }
            if ($uploadError !== UPLOAD_ERR_OK) {
                throw new AthleteCsvImportException('club.area.csv.upload_failed');
            }
            if ((int) ($file['size'] ?? 0) > AthleteCsvTransfer::MAX_BYTES) {
                throw new AthleteCsvImportException('club.area.csv.too_large');
            }

            $path = is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : '';
            if ($path === '' || !is_file($path) || (PHP_SAPI !== 'cli' && !is_uploaded_file($path))) {
                throw new AthleteCsvImportException('club.area.csv.upload_failed');
            }

            $result = (new AthleteCsvTransfer())->import($path, $clubId, $mergeIncomplete);
        } catch (AthleteCsvImportException $exception) {
            return [
                'type' => 'error',
                'message' => $this->errorMessage($exception),
                'report' => [],
                'omitted' => 0,
            ];
        }

        $report = [];
        foreach ($result->issues as $issue) {
            $report[] = [
                'row' => $issue->row,
                'identity' => $issue->identity,
                'message' => $this->issueMessage($issue),
                'existing_athlete_id' => $issue->existingAthleteId,
            ];
        }
        foreach ($result->reconciliations as $reconciliation) {
            $report[] = [
                'row' => $reconciliation->row,
                'identity' => $reconciliation->identity,
                'message' => $this->reconciliationMessage($reconciliation),
                'existing_athlete_id' => $reconciliation->existingAthleteId,
            ];
        }
        usort($report, static fn(array $left, array $right): int => $left['row'] <=> $right['row']);
        $hasImportedRows = $result->created + $result->updated + $result->unchanged > 0;

        return [
            'type' => $result->issues === [] ? 'success' : ($hasImportedRows ? 'warning' : 'error'),
            'message' => __(
                $result->issues === [] ? 'club.area.csv.import_success' : 'club.area.csv.import_with_issues',
                [
                    'created' => (string) $result->created,
                    'updated' => (string) $result->updated,
                    'unchanged' => (string) $result->unchanged,
                    'skipped' => (string) $result->skipped(),
                ]
            ),
            'report' => array_slice($report, 0, self::REPORT_LIMIT),
            'omitted' => max(0, count($report) - self::REPORT_LIMIT),
        ];
    }

    private function errorMessage(AthleteCsvImportException $exception): string
    {
        $replacements = $exception->replacements;
        if ($exception->row !== null) {
            $replacements['row'] = (string) $exception->row;
        }
        if ($exception->validationKeys !== []) {
            $replacements['errors'] = implode(' ', array_map(
                static fn(string $key): string => __($key),
                $exception->validationKeys
            ));
        }

        return __($exception->translationKey, $replacements);
    }

    private function issueMessage(AthleteImportIssue $issue): string
    {
        $replacements = ['row' => (string) $issue->row, 'athlete' => $issue->identity];
        if ($issue->validationKeys !== []) {
            $replacements['errors'] = implode(' ', array_map(
                static fn(string $key): string => __($key),
                $issue->validationKeys
            ));
        }
        if ($issue->fields !== []) {
            $replacements['fields'] = implode(', ', array_map(
                static fn(string $field): string => __('club.area.csv.headers.' . $field),
                $issue->fields
            ));
        }

        return __($issue->translationKey, $replacements);
    }

    private function reconciliationMessage(AthleteImportReconciliation $reconciliation): string
    {
        $details = [];
        foreach ($reconciliation->resolutions as $field => $resolution) {
            $details[] = __('club.area.csv.headers.' . $field)
                . ': ' . __('club.area.csv.reconciliation.' . $resolution);
        }

        return __('club.area.csv.reconciled', ['details' => implode('; ', $details)]);
    }
}
