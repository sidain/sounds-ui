<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OutputController extends Controller
{
    public function submit(Request $request)
    {

        $data = $request->input('files');

        // return response()->json([
        //     'received_data' => $data,
        // ]);

        // 8 if no files present in data, return a json response with an error message
        if( empty($data) ) {
            return response()->json(['error' => 'No data received'], 400);
        }

        // 1 if files present in data, created a new folder named {current date and time} in side the OUTPUT folder
        $folderName = date('Y-m-d_H-i-s');
        Storage::disk('OUTPUT')->makeDirectory($folderName);

        // 2 copy all files to that folder
        foreach ($data as $file) {
            // $sourcePath = 'INPUT/' . $file['directory'] . '/' . $file['name'];
            $sourcePath = trim($file['directory'], '/') . '/' . $file['name'];
            $destinationPath = $folderName . '/' . $file['name'];

            if (Storage::disk('INPUT')->exists($sourcePath)) {
                Storage::disk('OUTPUT')->put($destinationPath, Storage::disk('INPUT')->get($sourcePath));
            } else {
                return response()->json(['error' => "File not found: {$file['name']}"], 404);
            }
        }

        // 3 in that folder, convert all files to ogg, and strip new file of any metadata or video data
        foreach (Storage::disk('OUTPUT')->files($folderName) as $file) {
            $outputFilePath = $folderName . '/' . pathinfo($file, PATHINFO_FILENAME) . '.ogg';

            $inputFilePath = $folderName . '/' . basename($file);

            $ffmpegBinary = config('services.ffmpeg.path', 'ffmpeg');

            if (app()->environment('local')) {
                \Log::info('FFMPEG_BINARY_RESOLVED: ' . $ffmpegBinary);
            }

            $command = escapeshellarg($ffmpegBinary) . " -y -f wav -i " . escapeshellarg(Storage::disk('OUTPUT')->path($inputFilePath)) . " -vn -acodec libvorbis -q:a 5 -map_metadata -1 " . escapeshellarg(Storage::disk('OUTPUT')->path($outputFilePath)) . " 2>&1";

            exec($command, $output, $exitCode);

            if ($exitCode !== 0 || !Storage::disk('OUTPUT')->exists($outputFilePath)) {
                return response()->json([
                    'error' => "ffmpeg conversion failed for {$file}",
                    'ffmpeg_output' => implode(PHP_EOL, $output),
                ], 500);
            }

            // Delete the original file after conversion
            Storage::disk('OUTPUT')->delete($inputFilePath);
        }


        // 4 create a files.txt of filenames in the folder, one per line
        $fileList = Storage::disk('OUTPUT')->files($folderName);
        $fileListContent = implode(PHP_EOL, array_map(function ($file) {
            return basename($file);
        }, $fileList));
        Storage::disk('OUTPUT')->put($folderName . '/files.txt', $fileListContent);

        /*
            label rules, 
            label total length of25 chars
            {X} {Y}
            prfix with aa
            X 3 char category
            Y char filename

            template::

            local sounds = {
                -- Section, template
            {"aa {X} - {Y}}", "Interface/AddOns/MySharedMediaSounds/sounds/FILES/01_notification_ping.ogg"},

            -- example
            {"aa UI - Notification Ping", "Interface/AddOns/MySharedMediaSounds/sounds/FILES/01_notification_ping.ogg"},
            } 
        */
        // 5 create a files.lua with a list  of filenames in the folder, foloowing a template
        $luaContent = [];

        foreach ($fileList as $file) {
            $X = 'UI'; // Example category, you can modify this as needed
            $Y = pathinfo($file, PATHINFO_FILENAME);

            $luaTemplate =  "     {'aa {$X} - {$Y}', 'Interface/Addons/MySharedMediaSounds/sounds/FILES/{$folderName}/{$Y}.ogg'},";

            $luaContent[] = str_replace(['{$X}', '{$Y}'], [$X, $Y], $luaTemplate);
        }

        $luaContentString = implode(PHP_EOL, $luaContent);

        $luaContentString = 'local sounds = {' . PHP_EOL . $luaContentString . PHP_EOL . '}' . PHP_EOL;
        Storage::disk('OUTPUT')->put($folderName . '/files.lua', $luaContentString);


        // 6 zip all files into a single file named after folder name, and save it to the OUTPUT folder
        $zipFilePath = Storage::disk('OUTPUT')->path($folderName . '.zip');
        $zip = new \ZipArchive();

        if ($zip->open($zipFilePath, \ZipArchive::CREATE) === TRUE) {
            foreach (Storage::disk('OUTPUT')->files($folderName) as $file) {
                $zip->addFile(Storage::disk('OUTPUT')->path($file), basename($file));
            }
            $zip->close();
        } else {
            return response()->json(['error' => 'Failed to create zip file'], 500);
        }



        // 7 return a json response with the path to the zip file
        return response()->json(['message' => 'Data received successfully', 'data' => $data, 'folder' => $folderName, 'zip' => $folderName . '.zip'] );
    }
}
