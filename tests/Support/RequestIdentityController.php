<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

final class RequestIdentityController extends Controller
{
    public function show(Request $request): Response
    {
        return new Response($this->request === $request ? 'same-request' : 'different-request');
    }
}
