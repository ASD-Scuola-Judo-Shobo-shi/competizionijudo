<?php if (is_string($agreementsFeedback ?? null)) : ?>
    <div class="notice success" role="status">
        <?= e($agreementsFeedback) ?>
    </div>
<?php endif; ?>
