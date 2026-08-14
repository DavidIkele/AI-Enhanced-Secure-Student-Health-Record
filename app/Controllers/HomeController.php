<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Landing page. No sensitive data is exposed here; it is a static overview
 * with links to features implemented in later prompts.
 */
class HomeController extends BaseController
{
    public function index(): void
    {
        $this->render('home/index', [
            'title' => 'Welcome | Student Health Record System',
            'page' => 'home',
        ]);
    }
}