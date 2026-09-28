<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the superadmin platform overview.
     */
    public function index(): View
    {
        $activeBusinesses = Business::where('status', 'active')->count();
        $suspendedBusinesses = Business::where('status', 'inactive')->count();
        $totalUsers = User::count();

        return view('superadmin.dashboard', [
            'activeBusinesses' => $activeBusinesses,
            'suspendedBusinesses' => $suspendedBusinesses,
            'totalBusinesses' => $activeBusinesses + $suspendedBusinesses,
            'totalUsers' => $totalUsers,
        ]);
    }
}
