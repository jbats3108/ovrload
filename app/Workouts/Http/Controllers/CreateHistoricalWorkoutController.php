<?php

namespace App\Workouts\Http\Controllers;

use App\Routines\Models\Routine;
use App\Shared\Http\Controllers\Controller;
use App\Workouts\Data\History\HistoricalCreatePageData;
use Inertia\Inertia;
use Inertia\Response;

class CreateHistoricalWorkoutController extends Controller
{
    public function __invoke(Routine $routine): Response
    {
        return Inertia::render('history/Create', [
            'form' => HistoricalCreatePageData::fromRoutine($routine),
        ]);
    }
}
