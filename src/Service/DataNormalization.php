<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

/** Formatting only: identities, ownership, competition data and snapshots are preserved. */
final class DataNormalization
{
    private const FIELDS = [
        'clubs' => ['name', 'contact_first_name', 'contact_last_name', 'email', 'phone', 'address_line', 'postal_code', 'federal_code'],
        'athletes' => ['last_name', 'first_name', 'membership_number'],
    ];

    public function __construct(private readonly PDO $database)
    {
    }

    public function count(string $type): int
    {
        $this->fields($type);
        return (int) $this->database->query('SELECT COUNT(*) FROM ' . $type)->fetchColumn();
    }

    /**
     * @return list<array{id:int, label:string, club:string, fingerprint:string, changes:array<string, array{before:string, after:string}>}>
     */
    public function preview(string $type, int $offset): array
    {
        $fields = $this->fields($type);
        $columns = implode(', ', array_map(static fn(string $field): string => 'r.' . $field, $fields));
        $clubColumns = $type === 'athletes' ? 'r.club_id, c.name AS club_name' : 'r.name AS club_name';
        $join = $type === 'athletes' ? ' JOIN clubs c ON c.id = r.club_id' : '';
        $statement = $this->database->prepare(
            'SELECT r.id, ' . $columns . ', ' . $clubColumns . ' FROM ' . $type . ' r' . $join
            . ' ORDER BY r.id LIMIT 50 OFFSET ?'
        );
        $statement->bindValue(1, max(0, $offset), PDO::PARAM_INT);
        $statement->execute();
        $result = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $changes = $this->changes($type, $row);
            if ($changes !== []) {
                $result[] = [
                    'id' => (int) $row['id'],
                    'label' => $type === 'clubs' ? (string) $row['name'] : $row['last_name'] . ' ' . $row['first_name'],
                    'club' => (string) $row['club_name'],
                    'fingerprint' => $this->fingerprint($type, $row),
                    'changes' => $changes,
                ];
            }
        }
        return $result;
    }

    /** @param array<int, string> $expected Selected IDs and their server-stored preview fingerprints. */
    public function apply(string $type, array $expected): int
    {
        $fields = $this->fields($type);
        if ($expected === [] || count($expected) > 50) {
            throw new RuntimeException('normalization.select');
        }
        $this->database->beginTransaction();
        try {
            $columns = 'id, ' . implode(', ', $fields) . ($type === 'athletes' ? ', club_id' : '');
            $statement = $this->database->prepare(
                'SELECT ' . $columns . ' FROM ' . $type
                . ' WHERE id IN (' . implode(',', array_fill(0, count($expected), '?')) . ') ORDER BY id'
                . ($this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '')
            );
            $statement->execute(array_keys($expected));
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) !== count($expected)) {
                throw new RuntimeException('normalization.stale');
            }
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                if (!hash_equals($expected[$id], $this->fingerprint($type, $row))) {
                    throw new RuntimeException('normalization.stale');
                }
            }
            $updated = 0;
            foreach ($rows as $row) {
                $changes = $this->changes($type, $row);
                if ($changes === []) {
                    continue;
                }
                $assignments = array_map(static fn(string $field): string => $field . ' = ?', array_keys($changes));
                $update = $this->database->prepare(
                    'UPDATE ' . $type . ' SET ' . implode(', ', $assignments) . ' WHERE id = ?'
                );
                $update->execute([...array_column($changes, 'after'), (int) $row['id']]);
                $updated++;
            }
            $this->database->commit();
            return $updated;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    /** @return list<string> */
    private function fields(string $type): array
    {
        return self::FIELDS[$type] ?? throw new RuntimeException('normalization.type_invalid');
    }

    /** @param array<string, mixed> $row */
    private function fingerprint(string $type, array $row): string
    {
        $values = [$type, (int) $row['id'], (int) ($row['club_id'] ?? 0)];
        foreach ($this->fields($type) as $field) {
            $values[] = $row[$field];
        }
        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, array{before:string, after:string}>
     */
    private function changes(string $type, array $row): array
    {
        $changes = [];
        $names = new AthleteFieldReconciler();
        foreach ($this->fields($type) as $field) {
            if ($row[$field] === null) {
                continue;
            }
            $before = (string) $row[$field];
            $after = trim(preg_replace('/\s+/u', ' ', $before) ?? $before);
            if (in_array($field, ['last_name', 'first_name', 'contact_first_name', 'contact_last_name'], true)) {
                $after = $names->titleCaseName($after);
            } elseif ($field === 'email') {
                $after = mb_strtolower(trim($before), 'UTF-8');
            } elseif ($field === 'federal_code') {
                $after = mb_strtoupper($after, 'UTF-8');
            }
            // Leave whitespace-only required fields for the existing validation/edit workflow.
            if ($before !== $after && $after !== '') {
                $changes[$field] = ['before' => $before, 'after' => $after];
            }
        }
        return $changes;
    }
}
