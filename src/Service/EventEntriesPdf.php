<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\View;
use App\Model\Club;
use App\Model\Event;
use Dompdf\Dompdf;
use Dompdf\Options;

final class EventEntriesPdf
{
    public function __construct(private readonly View $view)
    {
    }

    /** @param list<array<string, mixed>> $athletes Normalized, sorted entries for the signed-in club. */
    public function render(Event $event, Club $club, array $athletes): string
    {
        $html = $this->view->render('events/entries_pdf', [
            'event' => $event,
            'club' => $club,
            'athletes' => $athletes,
            'generatedAt' => date('Y-m-d H:i T'),
        ], 'layouts/entries_pdf');
        $options = new Options();
        $options->setDefaultFont('DejaVu Sans');
        $options->setFontCache(sys_get_temp_dir());
        $options->setAllowedProtocols([]);
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4');
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();
        $pdf->getCanvas()->page_text(
            42,
            810,
            __('events.entries_pdf_page', ['page' => '{PAGE_NUM}', 'pages' => '{PAGE_COUNT}']),
            $pdf->getFontMetrics()->getFont('DejaVu Sans'),
            8,
            [0.2, 0.2, 0.2]
        );

        return $pdf->output();
    }
}
