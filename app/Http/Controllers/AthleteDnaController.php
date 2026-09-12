<?php

namespace App\Http\Controllers;

use App\Services\Analytics\AthleteDnaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman "Athlete DNA" — ringkasan analitik personal jangka panjang.
 * Controller sengaja tipis: seluruh perhitungan ada di AthleteDnaService.
 */
class AthleteDnaController extends Controller
{
    public function __construct(private AthleteDnaService $dna) {}

    public function index(Request $request): View
    {
        $data = $this->dna->forUser($request->user());

        return view('athlete-dna.index', $data);
    }
}
