<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

header('Location: ' . app_path('/event_details.php'));
exit;
