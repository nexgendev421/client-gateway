<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AdminSectionController extends Controller
{
    private const SECTIONS = [
        'activation-codes' => 'Activation Codes',
        'audit-logs' => 'Audit Logs',
        'admin-users' => 'Admin Users',
        'settings' => 'Settings',
    ];

    public function __invoke(string $section): View
    {
        abort_unless(array_key_exists($section, self::SECTIONS), 404);

        return view('admin.placeholder', [
            'activeNav' => $section,
            'pageTitle' => self::SECTIONS[$section],
        ]);
    }
}
