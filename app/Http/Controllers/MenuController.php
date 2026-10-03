<?php

namespace App\Http\Controllers;

use App\Support\Menu;

class MenuController extends Controller
{
    public function index()
    {
        return view('menu.index', ['groups' => Menu::visibleGroups()]);
    }
}
