<?php

declare(strict_types=1);

namespace App\Model;

use PDO;
use RuntimeException;

final class EventRegistrationOption
{
    public function __construct(
        public readonly int $id,
        public readonly int $event_id,
        public readonly string $name,
        public readonly int $fee_cents,
        public readonly bool $is_default,
        public readonly bool $is_active,
        public readonly int $sort_order,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['id'] ?? 0),
            (int) ($data['event_id'] ?? 0),
            (string) ($data['name'] ?? ''),
            (int) ($data['fee_cents'] ?? 0),
            !empty($data['is_default']),
            !array_key_exists('is_active', $data) || !empty($data['is_active']),
            (int) ($data['sort_order'] ?? 0),
        );
    }

    /** @return list<self> */
    public static function activeForEvent(int $eventId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, event_id, name, fee_cents, is_default, is_active, sort_order
             FROM event_registration_options
             WHERE event_id = ? AND is_active = 1
             ORDER BY sort_order ASC, id ASC'
        );
        $statement->execute([$eventId]);

        return array_map(
            static fn(array $row): self => self::fromArray($row),
            $statement->fetchAll() ?: []
        );
    }

    public static function activeForEventById(int $eventId, int $optionId): ?self
    {
        $statement = Database::connection()->prepare(
            'SELECT id, event_id, name, fee_cents, is_default, is_active, sort_order
             FROM event_registration_options
             WHERE id = ? AND event_id = ? AND is_active = 1'
        );
        $statement->execute([$optionId, $eventId]);
        $row = $statement->fetch();

        return is_array($row) ? self::fromArray($row) : null;
    }

    /**
     * @param list<array{id:int|null, name:string, fee_cents:int, is_default:bool}> $options
     */
    public static function synchronize(PDO $database, int $eventId, array $options): void
    {
        if ($options === []) {
            throw new RuntimeException('An event must have at least one registration option.');
        }

        $defaultCount = 0;
        $seenOptionIds = [];
        $seenNames = [];
        foreach ($options as $option) {
            $name = trim($option['name']);
            if (
                $name === ''
                || mb_strlen($name) > 120
                || $option['fee_cents'] < 0
                || $option['fee_cents'] > 4_294_967_295
            ) {
                throw new RuntimeException('A registration option is invalid.');
            }
            $normalizedName = mb_strtolower($name);
            if (isset($seenNames[$normalizedName])) {
                throw new RuntimeException('Registration option names must be unique.');
            }
            $seenNames[$normalizedName] = true;
            if ($option['is_default']) {
                $defaultCount++;
            }

            $optionId = $option['id'];
            if ($optionId === null) {
                continue;
            }
            if (isset($seenOptionIds[$optionId])) {
                throw new RuntimeException('A registration option was submitted more than once.');
            }
            $seenOptionIds[$optionId] = true;
        }
        if ($defaultCount !== 1) {
            throw new RuntimeException('An event must have exactly one default registration option.');
        }

        $database->prepare(
            'UPDATE event_registration_options
             SET is_default = 0, is_active = 0
             WHERE event_id = ?'
        )->execute([$eventId]);

        $update = $database->prepare(
            'UPDATE event_registration_options
             SET name = ?, fee_cents = ?, is_default = ?, is_active = 1, sort_order = ?
             WHERE id = ? AND event_id = ?'
        );
        $insert = $database->prepare(
            'INSERT INTO event_registration_options (
                event_id, name, fee_cents, is_default, is_active, sort_order
             ) VALUES (?, ?, ?, ?, 1, ?)'
        );

        foreach ($options as $position => $option) {
            $parameters = [
                trim($option['name']),
                $option['fee_cents'],
                $option['is_default'] ? 1 : 0,
                $position,
            ];
            $optionId = $option['id'] ?? null;
            if ($optionId !== null && $optionId > 0) {
                $update->execute([...$parameters, $optionId, $eventId]);
                if ($update->rowCount() === 0 && !self::belongsToEvent($database, $optionId, $eventId)) {
                    throw new RuntimeException('A registration option does not belong to this event.');
                }
                continue;
            }

            $insert->execute([$eventId, ...$parameters]);
        }
    }

    private static function belongsToEvent(PDO $database, int $optionId, int $eventId): bool
    {
        $statement = $database->prepare(
            'SELECT 1 FROM event_registration_options WHERE id = ? AND event_id = ?'
        );
        $statement->execute([$optionId, $eventId]);

        return $statement->fetchColumn() !== false;
    }
}
