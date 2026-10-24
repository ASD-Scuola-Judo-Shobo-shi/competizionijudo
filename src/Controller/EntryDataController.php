<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\AuthContext;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Model\ClubDataRightsDeclaration;
use App\Model\ClubTermsAcceptance;
use App\Model\Database;
use App\Model\Entry;
use App\Model\Event;
use App\Presentation\EntryDataComparison;
use App\Presentation\EntryOutlierReview;
use App\Service\EntryDataReviewException;
use App\Service\EntryDataReviewService;
use App\Service\EventEntriesCsvTransfer;
use App\Service\TournamentData;
use App\Validation\EntryDataInput;
use Throwable;

final class EntryDataController extends Controller
{
    public function clubReview(Request $request): Response
    {
        Session::start();
        $clubId = AuthContext::clubId();
        if ($clubId === null) {
            return $this->redirect('/clubs/login');
        }
        if ($request->method() === 'POST') {
            validate_csrf((string) $request->post('csrf_token'));
            if (!ClubDataRightsDeclaration::hasCurrentVersion($clubId) || !ClubTermsAcceptance::hasCurrentVersion($clubId)) {
                return $this->redirect('/clubs/agreements', 303);
            }
        }

        return $this->review($request, $clubId);
    }

    public function adminReview(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }
        if ($request->method() === 'POST') {
            validate_csrf((string) $request->post('csrf_token'));
        }

        return $this->review($request, null);
    }

    public function pending(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }
        $event = Event::findById((int) $request->query('event_id'));
        if ($event === null) {
            throw new HttpException(404, __('errors.page_not_found'));
        }

        return $this->view('entries/pending', [
            'title' => __('entry_review.pending_title'), 'event' => $event,
            'reviews' => (new EntryDataReviewService(Database::connection()))->pending($event->id),
        ]);
    }

    public function outliers(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }
        $event = Event::findById((int) $request->query('event_id'));
        if ($event === null) {
            throw new HttpException(404, __('errors.page_not_found'));
        }
        $review = new EntryOutlierReview(
            config('entry_review.weight_by_age'),
            config('entry_review.belt_minimum_ages')
        );
        $rows = $review->find(Entry::findByEvent($event->id, null, $event->closed, $event->date), $event->date);
        $pagination = paginate(count($rows), max(1, (int) $request->query('page', 1)), 50, 'page');

        return $this->view('entries/outliers', [
            'title' => __('entry_review.outliers.title'), 'event' => $event, 'pagination' => $pagination,
            'outliers' => array_slice($rows, $pagination['offset'], $pagination['per_page']),
        ]);
    }

    public function differences(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }
        $event = Event::findById((int) $request->query('event_id'));
        if ($event === null) {
            throw new HttpException(404, __('errors.page_not_found'));
        }
        $preview = (new EntryDataReviewService(Database::connection()))->alignmentPreview($event->id);
        $report = EntryDataComparison::fromRows(
            Entry::findByEvent($event->id, null, $event->closed, $event->date),
            (new EventEntriesCsvTransfer())->rows($event->id, $event->closed, eventDate: $event->date),
            $preview['entries'],
            $event->date,
            $event->closed
        );
        $pagination = paginate(
            count($report->differences),
            max(1, (int) $request->query('page', 1)),
            50,
            'page',
            ['event_id' => $event->id]
        );

        return $this->view('entries/differences', [
            'title' => __('entry_review.differences.title'), 'event' => $event, 'report' => $report,
            'differences' => array_slice($report->differences, $pagination['offset'], $pagination['per_page']),
            'pagination' => $pagination, 'fingerprint' => $preview['fingerprint'], 'canAlign' => $preview['canAlign'],
            'feedback' => Session::pullFlash('entry_alignment_' . $event->id),
        ])->withHeaders(['Cache-Control' => 'private, no-store, max-age=0']);
    }

    public function align(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }
        validate_csrf((string) $request->post('csrf_token'));
        $eventId = (int) $request->post('event_id');
        try {
            $count = (new EntryDataReviewService(Database::connection()))->alignCurrentProfiles(
                $eventId,
                $this->postString($request, 'fingerprint'),
                $this->postString($request, 'reason')
            );
            $feedback = __('entry_review.differences.aligned', ['count' => (string) $count]);
        } catch (EntryDataReviewException $exception) {
            if ($exception->getMessage() === 'not_found') {
                throw new HttpException(404, __('errors.page_not_found'));
            }
            $feedback = __('entry_review.errors.' . $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportFailure('entries.alignment_failed', $exception, $request);
            $feedback = __('errors.save_failed');
        }
        Session::flash('entry_alignment_' . $eventId, $feedback);

        return $this->redirect('/admin/events/differences?event_id=' . $eventId, 303);
    }

    private function review(Request $request, ?int $clubId): Response
    {
        $eventId = (int) filter_var($request->input('event_id'), FILTER_VALIDATE_INT);
        $entryId = (int) filter_var($request->input('entry_id'), FILTER_VALIDATE_INT);
        $service = new EntryDataReviewService(Database::connection());
        $error = null;
        $status = 200;
        $editData = null;
        try {
            if ($request->method() === 'POST') {
                $operation = $this->postString($request, 'operation');
                $reason = $this->postString($request, 'reason');
                $fingerprint = $this->postString($request, 'fingerprint');
                if ($operation === 'request' || ($clubId === null && $operation === 'apply_revision')) {
                    $service->requestRevision(
                        $eventId,
                        $entryId,
                        (int) filter_var($request->post('revision_id'), FILTER_VALIDATE_INT),
                        $clubId,
                        $reason,
                        $fingerprint,
                        $clubId === null
                    );
                } elseif ($clubId === null && $operation === 'edit') {
                    $editData = $this->editData($request);
                    $errors = EntryDataInput::errors($editData);
                    if ($errors !== []) {
                        $error = implode(' ', array_map(static fn(string $key): string => __($key), $errors));
                        $status = 422;
                    } else {
                        $data = $editData;
                        $data['weight_kg'] = $data['weight_kg'] !== '' ? $data['weight_kg'] : null;
                        $data['membership_number'] = $data['membership_number'] !== '' ? $data['membership_number'] : null;
                        $service->edit($eventId, $entryId, $data, $reason, $fingerprint);
                    }
                } elseif ($clubId === null && in_array($operation, ['approve', 'reject'], true)) {
                    $service->decide($eventId, $entryId, (int) $request->post('review_id'), $operation === 'approve', $reason);
                } else {
                    throw new EntryDataReviewException('invalid');
                }
                if ($error === null) {
                    Session::flash('entry_review_' . $entryId, __('entry_review.saved'));
                    $path = $clubId === null ? '/admin/events/entry-data' : '/events/entry-data';

                    return $this->redirect($path . '?event_id=' . $eventId . '&entry_id=' . $entryId, 303);
                }
            }
            $details = $service->details($eventId, $entryId, $clubId);
        } catch (EntryDataReviewException $exception) {
            if ($exception->getMessage() === 'not_found') {
                throw new HttpException(404, __('errors.page_not_found'));
            }
            $error = __('entry_review.errors.' . $exception->getMessage());
            $status = 409;
            $details = $service->details($eventId, $entryId, $clubId);
        } catch (Throwable $exception) {
            $this->reportFailure('entries.data_review_failed', $exception, $request);

            return new Response(e(__('errors.save_failed')), 500);
        }

        return $this->view('entries/review', array_merge($details, [
            'title' => __('entry_review.title'), 'administrator' => $clubId === null, 'error' => $error,
            'editData' => $editData ?? $details['current'],
            'feedback' => Session::pullFlash('entry_review_' . $entryId),
        ]), $status);
    }

    /** @return array<string, string> */
    private function editData(Request $request): array
    {
        $data = [];
        foreach (array_slice(TournamentData::FIELDS, 0, 7) as $field) {
            $value = $request->post($field);
            $data[$field] = is_string($value) ? trim($value) : '';
        }
        $data['weight_kg'] = str_replace(',', '.', $data['weight_kg']);

        return $data;
    }

    private function postString(Request $request, string $field): string
    {
        $value = $request->post($field);

        return is_string($value) ? $value : '';
    }
}
