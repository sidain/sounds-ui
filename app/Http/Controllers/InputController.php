<?php

namespace App\Http\Controllers;

ini_set('memory_limit', '2048M');
set_time_limit(120);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InputController extends Controller
{
    // public function index()
    // {
    //     $allfiles = Storage::disk('INPUT')->files();

    //     // following should be any BUT ZIP files
    //     $files = array_filter($allfiles, function ($file) {
    //         return pathinfo($file, PATHINFO_EXTENSION) !== 'zip';
    //     });

    //     $zipFiles = array_filter($allfiles, function ($file) {
    //         return pathinfo($file, PATHINFO_EXTENSION) === 'zip';
    //     });

    //     $directories = Storage::disk('INPUT')->directories();

    //     foreach ($directories as $directory) {
    //         $subFiles = Storage::disk('INPUT')->files($directory);
    //         $files[basename($directory)] = array_merge($files, $subFiles);            
    //     }

    //     return response()->json([
    //         'zip_files' => $zipFiles,
    //         'files' => $files,
    //         'directories' => $directories,
    //     ]);

    // }

    public function index()
    {
        $directories = Storage::disk('INPUT')->directories();
        $allfiles = Storage::disk('INPUT')->files();

        $zipFiles = array_filter($allfiles, function ($file) {
            return pathinfo($file, PATHINFO_EXTENSION) === 'zip';
        });

        $files = [];
        $subFiles = [];
        $usedDirectories = [];
        $totalFilesCount = 0;

        // intial files displayed
        foreach ($directories as $directory) {
            $subFiles = Storage::disk('INPUT')->files($directory);
            
            // $files[basename($directory)] = array_merge($files, $subFiles);
            $files[basename($directory)] = $subFiles;

            $usedDirectories[] = $directory;

            $totalFilesCount += count($subFiles); // Add this directory's file count to the total

            if ($totalFilesCount > 18) {
                break;
            }             
        }

        // dump($files);


        return response()->json([
            'zip_files' => $zipFiles,
            'files' => $files,
            'directories' => $directories,
            'usedDirectories' => $usedDirectories,
        ]);
    }

    public function getFiles(Request $request)
    {
        $directory = $request->input('directory');

        if (!$directory || !Storage::disk('INPUT')->exists($directory)) {
            return response()->json(['error' => 'Directory not found'], 404);
        }

        $files = Storage::disk('INPUT')->files($directory);
        return response()->json($files);
    }


}
