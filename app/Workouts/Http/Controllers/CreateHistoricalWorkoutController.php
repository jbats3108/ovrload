<?php

namespace App\Workouts\Http\Controllers;

use App\Routines\Models\Routine;
use App\Shared\Http\Controllers\Controller;
use App\Users\Services\PlateProfileService;
use App\Workouts\Data\History\HistoricalCreatePageData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreateHistoricalWorkoutController extends Controller
{
    public function __invoke(Request $request, Routine $routine, PlateProfileService $profiles): Response
    {
        return Inertia::render('history/Create', [
            'form' => HistoricalCreatePageData::fromRoutine($routine),
            'plate_profile' => $profiles->profilePayloadFor($request->user()),
        ]);
    }
}
