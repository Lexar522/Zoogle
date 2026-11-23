<?php

namespace App\Http\Controllers;

use App\Models\ShopCharitySetting;
use Illuminate\View\View;

class CharityController extends Controller
{
    public function __invoke(): View
    {
        $charity = ShopCharitySetting::resolved();
        abort_unless($charity['is_enabled'], 404);

        return view('charity.show', ['charity' => $charity]);
    }
}
