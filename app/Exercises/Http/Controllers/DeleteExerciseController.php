<?php

namespace App\Exercises\Http\Controllers;

use App\Exercises\Models\Exercise;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class DeleteExerciseController extends Controller
{
    public function __invoke(Exercise $exercise): RedirectResponse
    {
        $wasCustom = $exercise->isCustom();

        $exercise->delete();

        if ($wasCustom) {
            return back()->with('success', 'Custom exercise deleted.');
        }

        return redirect()
            ->route('admin.exercises')
            ->with('success', 'Exercise deleted.');
    }
}
