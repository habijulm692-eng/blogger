<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use App\Models\ActivityLog;

class ActivityController
{
    public function index(): void
    {
        $logs = ActivityLog::getRecent(100);

        Response::view('settings.activity', [
            'logs' => $logs,
        ]);
    }
}
