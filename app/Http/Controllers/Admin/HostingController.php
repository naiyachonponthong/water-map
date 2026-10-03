<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\HostingStatus;

class HostingController extends Controller
{
    public function index(HostingStatus $status)
    {
        $checks = $status->checks();
        $ready = ! array_filter($checks, fn ($c) => ! $c['ok']);

        return view('admin.hosting', compact('checks', 'ready'));
    }
}
