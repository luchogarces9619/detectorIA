<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\System\SystemHealthService;
use Illuminate\View\View;

class SystemStatusController extends Controller
{
    public function __invoke(SystemHealthService $health): View
    {
        return view('admin.system', [
            'health' => $health->snapshot(),
        ]);
    }
}
