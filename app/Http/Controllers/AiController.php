<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class AiController extends Controller
{
    public function index(): View
    {
        return view('ai.index');
    }
}
