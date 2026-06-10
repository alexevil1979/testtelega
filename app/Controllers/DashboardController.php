<?php

/**
 * Главная страница дашборда и раздел «Действия».
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Services\TelegramService;
use App\View;

final class DashboardController extends BaseController
{
    public function index(): void
    {
        $user = TelegramService::getSelf();
        $isLoggedIn = TelegramService::isLoggedIn();

        View::render('pages/dashboard', [
            'title' => 'Дашборд',
            'page' => 'dashboard',
            'user' => $user,
            'isLoggedIn' => $isLoggedIn,
        ]);
    }

    public function actions(): void
    {
        View::render('pages/actions', [
            'title' => 'Действия',
            'page' => 'actions',
            'isLoggedIn' => TelegramService::isLoggedIn(),
        ]);
    }
}
