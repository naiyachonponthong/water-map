<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class DataHealthController extends Controller
{
    public function index()
    {
        return view('admin.data-health', ['province' => $this->province()]);
    }
}
