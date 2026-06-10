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
        $state = TelegramService::getLoginState();

        View::render('pages/dashboard', [
            'title' => 'Дашборд',
            'page' => 'dashboard',
            'isLoggedIn' => $state['logged_in'],
        ]);
    }

    public function actions(): void
    {
        View::render('pages/actions', [
            'title' => 'Действия',
            'page' => 'actions',
            'isLoggedIn' => TelegramService::getLoginState()['logged_in'],
        ]);
    }
}
