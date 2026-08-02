<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Club;
use App\Model\Event;

final class RegistrationRecapFormatter
{
    public static function subject(Event $event): string
    {
        return __('events.registration_recap_email_subject', ['event' => $event->name]);
    }

    /**
     * @param array<string, int> $feedback
     * @param array<string, mixed> $summary
     */
    public static function plainText(Event $event, Club $club, array $feedback, array $summary): string
    {
        $lines = [
            __('events.payment_summary_title'),
            '',
            __('events.registration_recap_event'),
            __('events.name') . ': ' . $event->name,
            __('events.date') . ': ' . $event->date,
            __('events.location') . ': ' . $event->location,
        ];

        self::appendOptionalLine($lines, __('events.organizer'), $event->organizer);
        self::appendOptionalLine($lines, __('events.registration_deadline'), $event->registration_deadline);

        $lines[] = '';
        $lines[] = __('events.registration_recap_club');
        $lines[] = __('club.list.table_name') . ': ' . $club->name;
        $lines[] = __('club.list.table_code') . ': ' . $club->federal_code;
        $lines[] = '';
        $lines[] = __('events.registration_feedback_title');
        self::appendFeedback($lines, $feedback);
        $lines[] = '';
        $lines[] = __('events.registration_option_selected') . ': '
            . (string) ($summary['selected_option_name'] ?? '')
            . ' (' . RegistrationPaymentService::formatAmount(
                max(0, (int) ($summary['selected_option_fee_cents'] ?? 0))
            ) . ')';

        self::appendEnrollments(
            $lines,
            __('events.payment_summary_new_enrollments'),
            $summary['new_enrollments'] ?? null,
            '+'
        );
        self::appendEnrollments(
            $lines,
            __('events.payment_summary_removed_enrollments'),
            $summary['removed_enrollments'] ?? null,
            '-'
        );

        $lines[] = '';
        $lines[] = __('events.payment_summary_new_total') . ': +'
            . RegistrationPaymentService::formatAmount(max(0, (int) ($summary['new_enrollment_cents'] ?? 0)));
        $lines[] = __('events.payment_summary_removed_total') . ': -'
            . RegistrationPaymentService::formatAmount(max(0, (int) ($summary['removed_enrollment_cents'] ?? 0)));
        $amountDueCents = max(0, (int) ($summary['amount_due_cents'] ?? 0));
        $lines[] = __('events.amount_due') . ': ' . RegistrationPaymentService::formatAmount($amountDueCents);
        $creditCents = max(0, (int) ($summary['credit_cents'] ?? 0));
        if ($creditCents > 0) {
            $lines[] = __('events.payment_summary_credit') . ': '
                . RegistrationPaymentService::formatAmount($creditCents);
        }
        $lines[] = __('events.payment_summary_total_athletes') . ': '
            . max(0, (int) ($summary['total_athletes'] ?? 0));

        $paymentInfo = is_array($summary['payment_info'] ?? null) ? $summary['payment_info'] : null;
        if ($amountDueCents > 0 && $paymentInfo !== null) {
            $lines[] = '';
            $lines[] = __('events.payment_info');
            $lines[] = __('events.payment_account_holder') . ': '
                . (string) ($paymentInfo['account_holder'] ?? '');
            $lines[] = __('events.payment_iban') . ': ' . (string) ($paymentInfo['iban'] ?? '');
            self::appendOptionalLine($lines, __('events.payment_bic'), $paymentInfo['bic'] ?? null);
            self::appendOptionalLine(
                $lines,
                __('events.payment_reason'),
                $summary['payment_reason'] ?? null
            );
        } elseif ($amountDueCents === 0) {
            $lines[] = '';
            $lines[] = __('events.payment_not_required');
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<string> $lines
     * @param array<string, int> $feedback
     */
    private static function appendFeedback(array &$lines, array $feedback): void
    {
        $messages = [
            'added' => 'events.registration_added',
            'removed' => 'events.unregistration_removed',
            'rejected' => 'events.registration_rejected',
            'missing_weight' => 'events.registration_missing_weight',
            'capacity_exceeded' => 'events.registration_capacity_exceeded',
            'failed' => 'events.registration_failed',
        ];
        foreach ($messages as $key => $translationKey) {
            $count = max(0, (int) ($feedback[$key] ?? 0));
            if ($count > 0) {
                $lines[] = __($translationKey, ['count' => (string) $count]);
            }
        }
    }

    /** @param list<string> $lines */
    private static function appendEnrollments(array &$lines, string $heading, mixed $enrollments, string $sign): void
    {
        $lines[] = '';
        $lines[] = $heading;
        if (!is_array($enrollments) || $enrollments === []) {
            $lines[] = '—';

            return;
        }

        foreach ($enrollments as $enrollment) {
            if (!is_array($enrollment)) {
                continue;
            }
            $lines[] = sprintf(
                '- %s — %s: %s%s',
                (string) ($enrollment['athlete_name'] ?? ''),
                (string) ($enrollment['option_name'] ?? ''),
                $sign,
                RegistrationPaymentService::formatAmount(max(0, (int) ($enrollment['fee_cents'] ?? 0)))
            );
        }
    }

    /** @param list<string> $lines */
    private static function appendOptionalLine(array &$lines, string $label, mixed $value): void
    {
        $value = trim((string) $value);
        if ($value !== '') {
            $lines[] = $label . ': ' . $value;
        }
    }
}
