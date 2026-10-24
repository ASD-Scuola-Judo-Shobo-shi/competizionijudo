<?php

declare(strict_types=1);

/** @var string $content */
?>
<!DOCTYPE html>
<html lang="<?= e(\App\Localization::getLocale()) ?>">
<head>
    <meta charset="UTF-8">
    <title><?= e(__('events.entries_pdf_title')) ?></title>
    <style>
        @page { margin: 15mm 15mm 18mm; }
        body { font-family: "DejaVu Sans", sans-serif; color: #20272d; font-size: 9pt; line-height: 1.25; }
        .document-heading { border-left: 5pt solid #216477; background: #edf5f7; padding: 12pt; margin-bottom: 16pt; }
        .document-heading p { margin: 0 0 4pt; }
        h1 { font-size: 20pt; margin: 0 0 8pt; }
        h2 { font-size: 13pt; margin: 0 0 6pt; }
        .document-meta { margin-bottom: 14pt; }
        .document-meta p { margin: 3pt 0; }
        .document-total { font-weight: bold; }
        .club-roster + .club-roster { page-break-before: always; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { border: 0.5pt solid #7c858b; padding: 4pt; text-align: left; vertical-align: top; overflow-wrap: break-word; }
        th { background: #deedf1; color: #20272d; font-size: 8pt; }
        tbody tr:nth-child(even) { background: #f3f5f6; }
        .column-number { width: 5%; }
        .column-athlete { width: 28%; }
        .column-age { width: 16%; }
        .column-gender { width: 12%; }
        .column-weight { width: 11%; }
        .column-category { width: 15%; }
        .column-belt { width: 13%; }

        /* PDF positioning for the shared archive belt badge markup. */
        .belt-badge { display: block; padding: 3pt 2pt; border: 0.5pt solid #d4deeb; border-radius: 8pt; background: #f3f6fa; color: #24364f; font-size: 7pt; font-weight: bold; line-height: 1.2; text-align: center; }
        .belt-badge__visual { position: relative; display: block; width: 26pt; height: 14pt; margin: 0 auto 2pt; }
        .belt-badge__band, .belt-badge__knot { position: absolute; display: block; overflow: hidden; border: 0.5pt solid #64748b; border-radius: 2pt; background: #e5e7eb; }
        .belt-badge__band { top: 4.5pt; left: 0; width: 26pt; height: 6pt; transform: skewX(-5deg); }
        .belt-badge__knot { top: 1.5pt; left: 9pt; width: 8pt; height: 11pt; transform: rotate(2deg); }
        .belt-badge__segment { position: absolute; display: block; top: 0; left: 0; width: 100%; height: 100%; }
        .belt-badge__segment + .belt-badge__segment { left: 50%; width: 50%; }
        .belt-badge__segment--c-f5f5f5 { background-color: #f5f5f5; }
        .belt-badge__segment--c-ffc107 { background-color: #ffc107; }
        .belt-badge__segment--c-fd7e14 { background-color: #fd7e14; }
        .belt-badge__segment--c-4caf50 { background-color: #4caf50; }
        .belt-badge__segment--c-214284 { background-color: #214284; }
        .belt-badge__segment--c-795548 { background-color: #795548; }
        .belt-badge__segment--c-424242 { background-color: #424242; }
        .belt-badge__segment--c-d32f2f { background-color: #d32f2f; }
        .belt-badge__segment--c-9ca3af { background-color: #9ca3af; }
        .belt-badge__label { display: block; white-space: normal; }
    </style>
</head>
<body>
    <?= $content ?>
</body>
</html>
