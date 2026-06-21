<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Model\Competition;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('home/index', [
            'title' => 'Dashboard',
            'competitions' => Competition::upcoming(),
        ]);
    }

    public function about(Request $request): Response
    {
        return $this->view('home/about', [
            'title' => 'About',
        ]);
    }
}
