<section class="page-heading">
    <p class="eyebrow">500</p>
    <h1><?= e($title ?? __('errors.server_error')) ?></h1>
    <p><?= e($message ?? __('errors.server_error')) ?></p>
    <?php if (!empty($reference)) : ?>
        <p><?= e($reference) ?></p>
    <?php endif; ?>
</section>
