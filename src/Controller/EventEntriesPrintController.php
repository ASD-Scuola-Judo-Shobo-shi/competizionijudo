<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\AuthContext;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Model\Club;
use App\Model\Entry;
use App\Model\Event;
use App\Presentation\EventEntriesViewModel;
use App\Service\EventEntriesPdf;

final class EventEntriesPrintController extends Controller
{
    public function download(Request $request): Response
    {
        Session::start();
        $clubId = AuthContext::clubId();
        if ($clubId === null) {
            return $this->redirect('/clubs/login');
        }

        $eventId = (int) $request->query('event', '0');
        $event = $eventId > 0 ? Event::findPublishedByIdOrClosedWithEntries($eventId, $clubId) : null;
        if ($event === null) {
            return $this->redirect('/events');
        }
        $club = Club::findById($clubId);
        if ($club === null) {
            return $this->redirect('/clubs/login');
        }

        $rows = Entry::findByEvent($eventId, $clubId, $event->closed);
        $report = EventEntriesViewModel::fromRows($rows, [], $clubId);
        $pdf = (new EventEntriesPdf($this->view))->render(
            $event,
            $club,
            $report->sortedCurrentClubEntries('athlete', 'asc')
        );

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="club-athletes-event-' . $eventId . '.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
