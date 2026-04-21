<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\View;

final class DashboardController
{
    public function __construct(private readonly \App\Core\App $app) {}

    public function index(Request $request): void
    {
        View::render('dashboard');
    }
}
