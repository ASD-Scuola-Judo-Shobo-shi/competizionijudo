<?php

/** @var \App\Model\Event $event */
/** @var list<array<string, mixed>> $enrolledAthletes */
/** @var list<array<string, mixed>> $enrolledClubs */
/** @var list<string> $enrollmentFields */
/** @var int|null $selectedEnrollmentClubId */
/** @var array{page:int, per_page:int, total:int, last_page:int, offset:int, links:string} $enrollmentPagination */
$entryTypePresentation = static function (string $value): array {
    $typeKey = match (mb_strtolower(trim($value), 'UTF-8')) {
        'pre-competitive', 'precompetitive',
        'pre-agonistico', 'preagonistico' => 'precompetitive',
        'competitive', 'agonistico' => 'competitive',
        default => null,
    };

    return $typeKey !== null
        ? [__('admin.events.table.' . $typeKey), __('admin.events.type_tooltip.' . $typeKey)]
        : [$value, $value];
};
?>

<section class="card admin-event-enrollments">
    <h2>
        <?= e(__('admin.event_details.enrolled_athletes')) ?>
        (<?= e((string) $enrollmentPagination['total']) ?>)
    </h2>
    <?php if ($enrolledClubs !== []) : ?>
        <form
            method="get"
            action="<?= e(base_url('/admin/events/details')) ?>"
            class="closed-event-club-filter"
        >
            <input type="hidden" name="event_id" value="<?= e((string) $event->id) ?>">
            <div class="closed-event-club-filter__field">
                <label for="event-enrollment-club-filter">
                    <?= e(__('admin.event_details.filter_club')) ?>
                </label>
                <select id="event-enrollment-club-filter" name="club_id">
                    <option value=""><?= e(__('admin.event_details.all_clubs')) ?></option>
                    <?php foreach ($enrolledClubs as $enrolledClub) : ?>
                        <?php $enrolledClubId = (int) ($enrolledClub['id'] ?? 0); ?>
                        <option
                            value="<?= e((string) $enrolledClubId) ?>"
                            <?= $selectedEnrollmentClubId === $enrolledClubId ? 'selected' : '' ?>
                        ><?= e((string) ($enrolledClub['club_name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn" type="submit"><?= e(__('admin.event_details.apply_filter')) ?></button>
        </form>
    <?php endif; ?>
    <?php if ($enrolledAthletes === []) : ?>
        <p><?= e(__('admin.event_details.no_enrolled_athletes')) ?></p>
    <?php else : ?>
        <div
            class="table-scroll table-scroll--wide table-scroll--responsive"
            role="region"
            tabindex="0"
            aria-label="<?= e(__('admin.event_details.enrolled_athletes')) ?>"
        >
            <table class="responsive-table">
                <thead>
                    <tr>
                        <?php foreach ($enrollmentFields as $field) : ?>
                            <?php $fieldLabel = __('admin.event_details.enrollment_fields.' . $field); ?>
                            <th scope="col" title="<?= e($fieldLabel) ?>">
                                <abbr title="<?= e($fieldLabel) ?>">
                                    <?= e(__('admin.event_details.enrollment_field_abbreviations.' . $field)) ?>
                                </abbr>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enrolledAthletes as $enrolledAthlete) : ?>
                        <tr>
                            <?php foreach ($enrollmentFields as $field) : ?>
                                <?php
                                $value = $enrolledAthlete[$field] ?? '';
                                if ($field === 'weight_kg' && is_numeric($value)) {
                                    $value = rtrim(rtrim(sprintf('%.2F', (float) $value), '0'), '.');
                                }
                                $fieldLabel = __('admin.event_details.enrollment_fields.' . $field);
                                ?>
                                <td data-label="<?= e($fieldLabel) ?>">
                                    <?php if ($field === 'gender') : ?>
                                        <?php
                                        $genderBadge = \App\Model\Gender::tryFromValue((string) $value);
                                        $genderBadgeFallback = (string) $value;
                                        require dirname(__DIR__) . '/components/gender_badge.php';
                                        ?>
                                    <?php elseif ($field === 'belt') : ?>
                                        <?php
                                        $beltBadge = \App\Model\Belt::tryFromValue((string) $value);
                                        $beltBadgeFallback = (string) $value;
                                        require dirname(__DIR__) . '/components/belt_badge.php';
                                        ?>
                                    <?php elseif ($field === 'type') : ?>
                                        <?php [$typeAbbreviation, $typeLabel] = $entryTypePresentation((string) $value); ?>
                                        <span class="table-density-value" title="<?= e($typeLabel) ?>">
                                            <?= e($typeAbbreviation) ?>
                                        </span>
                                        <span class="card-density-value"><?= e($typeLabel) ?></span>
                                    <?php elseif ($field === 'birth_date' && (string) $value !== '') : ?>
                                        <time datetime="<?= e((string) $value) ?>"><?= e((string) $value) ?></time>
                                    <?php else : ?>
                                        <?= e((string) $value) ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $enrollmentPagination['links'] ?>
    <?php endif; ?>
</section>
