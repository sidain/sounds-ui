<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


use App\Http\Controllers\InputController;
Route::get('/input-files', [InputController::class, 'index']);

use App\Http\Controllers\OutputController;
Route::post('/submit-sounds', [OutputController::class, 'submit']);

Route::get('/get-files', [InputController::class, 'getFiles']);

Route::get('/play-audio', function (Request $request) {
    $path = $request->query('path');

    // Assuming your disk configuration points to the root/FILES/INPUT directory
    if (!Storage::disk('INPUT')->exists($path)) {
        abort(404, 'File not found.');
    }

    return Storage::disk('INPUT')->response($path);
});
