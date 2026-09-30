<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboard,
    ) {
    }

    /**
     * Display the ERP dashboard.
     */
    public function __invoke(): InertiaResponse
    {
        $user = request()->user();

        abort_unless($user !== null, Response::HTTP_UNAUTHORIZED);

        return Inertia::render(
            'Dashboard',
            $this->dashboard->data($user),
        );
    }
}
