<section class="content-panel error-card" aria-labelledby="error-title">
    <p class="error-code" aria-hidden="true">🔒 419</p>
    <h1 id="error-title"><?= e($title ?? __('errors.invalid_csrf')) ?></h1>
    <p class="error-description"><?= e(__('errors.invalid_csrf_description')) ?></p>
    <div class="error-actions">
        <a class="btn green" href="/"><?= e(__('errors.go_home')) ?></a>
        <a class="btn" href="/events.php"><?= e(__('errors.view_events')) ?></a>
    </div>
</section>
