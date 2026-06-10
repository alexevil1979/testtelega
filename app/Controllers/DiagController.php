<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Bootstrap;
use App\Services\MadelineEnvironment;
use App\View;

final class DiagController extends BaseController
{
    public function madeline(): void
    {
        MadelineEnvironment::prepare();

        $sessionsPath = Bootstrap::config('app')['paths']['sessions'];
        $sessions = [];
        foreach (glob($sessionsPath . '/*.madeline', GLOB_ONLYDIR) ?: [] as $dir) {
            $sessions[] = [
                'name' => basename($dir),
                'light_state' => is_file($dir . '/lightState.php'),
                'ipc_socket' => is_dir($dir . '/ipc') || file_exists($dir . '/ipc'),
                'lock' => file_exists($dir . '/lock'),
            ];
        }

        View::json([
            'madeline' => MadelineEnvironment::getDiagnostics(),
            'sessions' => $sessions,
        ]);
    }
}
