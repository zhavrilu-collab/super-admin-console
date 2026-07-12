<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\Admin\AdminSaaSService;
use App\Support\AdminSession;
use Illuminate\Http\RedirectResponse;

class AdminAppController extends Controller
{
    public function switchApp(Application $application): RedirectResponse
    {
        session()->put(AdminSession::ACTIVE_APP_ID, $application->id);

        return redirect()
            ->back()
            ->with('status', 'Aktivna aplikacija: '.$application->name);
    }
}
