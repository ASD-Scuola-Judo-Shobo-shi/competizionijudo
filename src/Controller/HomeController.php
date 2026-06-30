<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('home/index', ['title' => 'Dashboard']);
    }

    public function about(Request $request): Response
    {
        return $this->view('home/about', [
            'title' => 'About',
        ]);
    }

    public function privacy(Request $request): Response
    {
        return $this->view('home/privacy', [
            'title' => __('privacy.title'),
            'privacy' => config('privacy', []),
        ]);
    }
}
