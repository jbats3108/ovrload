<?php

namespace App\Admin\Http\Controllers;

use App\ExerciseProfiles\Services\ExerciseProfileService;
use App\Shared\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ShowAdminExerciseProfilesController extends Controller
{
    public function __invoke(ExerciseProfileService $profiles): Response
    {
        return Inertia::render('admin/ExerciseProfiles', $profiles->adminPageData()->toArray());
    }
}
