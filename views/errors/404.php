<section class="content-panel error-card" aria-labelledby="error-title">
    <p class="error-code" aria-hidden="true">🔍 404</p>
    <h1 id="error-title"><?= e($title ?? __('errors.page_not_found')) ?></h1>
    <p class="error-description"><?= e(__('errors.page_not_found_description')) ?></p>
    <div class="error-actions">
        <a class="btn green" href="/"><?= e(__('errors.go_home')) ?></a>
        <a class="btn" href="/events.php"><?= e(__('errors.view_events')) ?></a>
    </div>
</section>
