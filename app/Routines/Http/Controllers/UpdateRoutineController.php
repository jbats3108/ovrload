<?php

namespace App\Routines\Http\Controllers;

use App\ExerciseProfiles\Exceptions\ExerciseProfileNotEditableException;
use App\Routines\Data\Editor\SyncRoutineData;
use App\Routines\Exceptions\RoutineStaleException;
use App\Routines\Models\Routine;
use App\Routines\Services\RoutineEditorService;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class UpdateRoutineController extends Controller
{
    public function __invoke(SyncRoutineData $data, Routine $routine, RoutineEditorService $editor): Response
    {
        try {
            $routine = $editor->sync($routine, $data);
        } catch (RoutineStaleException $e) {
            throw ValidationException::withMessages(['expected_updated_at' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['blocks' => $e->getMessage()]);
        } catch (ExerciseProfileNotEditableException $e) {
            throw ValidationException::withMessages(['default_exercise_profile_id' => $e->getMessage()]);
        }

        session()->flash('success', 'Routine saved.');

        return Inertia::location(route('routines.edit', $routine));
    }
}
