<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

header('Location: ' . base_url('/event_details.php'));
exit;
