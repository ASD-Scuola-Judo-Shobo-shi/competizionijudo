<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

final class AboutController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->redirect('/events');
    }

    public function about(Request $request): Response
    {
        return $this->view('static/about', [
            'title' => __('about.title'),
        ]);
    }

    public function privacy(Request $request): Response
    {
        return $this->view('static/privacy', [
            'title' => __('privacy.title'),
            'privacy' => config('privacy', []),
        ]);
    }
}
