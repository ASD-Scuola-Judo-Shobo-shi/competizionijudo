<section class="content-panel error-card" aria-labelledby="error-title">
    <p class="error-code" aria-hidden="true">💥 500</p>
    <h1 id="error-title"><?= e($title ?? __('errors.server_error')) ?></h1>
    <p class="error-description"><?= e($message ?? __('errors.unexpected_failure')) ?></p>
    <?php if (!empty($reference)) : ?>
        <p class="error-reference"><?= e($reference) ?></p>
    <?php endif; ?>
    <div class="error-actions">
        <a class="btn green" href="/"><?= e(__('errors.go_home')) ?></a>
    </div>
</section>
