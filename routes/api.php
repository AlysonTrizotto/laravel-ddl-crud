<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Checklist\PhotoAnnotationController;

Route::apiResource('photo-annotations', PhotoAnnotationController::class);
