<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Session;

final class EventRegistrationSelection
{
    private readonly string $sessionKey;

    public function __construct(int $clubId, int $eventId)
    {
        $this->sessionKey = 'event_registration_selection_' . $clubId . '_' . $eventId;
    }

    /** @return array<int, bool> */
    public function changes(): array
    {
        $stored = Session::get($this->sessionKey, []);
        $changes = [];
        if (is_array($stored) && is_array($stored['changes'] ?? null)) {
            foreach ($stored['changes'] as $athleteId => $selected) {
                if (is_int($athleteId) && $athleteId > 0 && is_bool($selected)) {
                    $changes[$athleteId] = $selected;
                }
            }
        }

        return $changes;
    }

    public function optionId(): ?int
    {
        $stored = Session::get($this->sessionKey, []);
        $optionId = is_array($stored) ? ($stored['option_id'] ?? null) : null;

        return is_int($optionId) && $optionId > 0 ? $optionId : null;
    }

    /**
     * Only IDs from the server-resolved club page can enter the draft.
     *
     * @param list<int> $editableAthleteIds
     * @param list<int> $registeredAthleteIds
     * @return int Number of rejected submitted IDs.
     */
    public function updatePage(
        array $editableAthleteIds,
        mixed $submittedAthleteIds,
        array $registeredAthleteIds,
        ?int $optionId
    ): int {
        $submittedAthleteIds = is_array($submittedAthleteIds) ? $submittedAthleteIds : [$submittedAthleteIds];
        $editable = array_fill_keys($editableAthleteIds, true);
        $registered = array_fill_keys($registeredAthleteIds, true);
        $selected = [];
        $rejected = 0;
        foreach ($submittedAthleteIds as $athleteId) {
            if (is_numeric($athleteId) && isset($editable[(int) $athleteId])) {
                $selected[(int) $athleteId] = true;
            } else {
                $rejected++;
            }
        }

        $changes = $this->changes();
        foreach ($editableAthleteIds as $athleteId) {
            $isSelected = isset($selected[$athleteId]);
            if ($isSelected === isset($registered[$athleteId])) {
                unset($changes[$athleteId]);
            } else {
                $changes[$athleteId] = $isSelected;
            }
        }
        Session::set($this->sessionKey, ['changes' => $changes, 'option_id' => $optionId]);

        return $rejected;
    }

    /**
     * @param list<int> $registeredAthleteIds
     * @return list<int>
     */
    public function selectedAthleteIds(array $registeredAthleteIds): array
    {
        $selected = array_fill_keys($registeredAthleteIds, true);
        foreach ($this->changes() as $athleteId => $isSelected) {
            if ($isSelected) {
                $selected[$athleteId] = true;
            } else {
                unset($selected[$athleteId]);
            }
        }

        return array_keys($selected);
    }

    public function clear(): void
    {
        Session::delete($this->sessionKey);
    }
}
