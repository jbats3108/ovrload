<?php

namespace App\Shared\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ShowTutorialController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Tutorial');
    }
}
