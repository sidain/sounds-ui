<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sound UI 🎵</title>

    <!-- This loads Vite's asset pipeline -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <!-- This is the root container where Vue will mount our audio file manager -->
    <div id="app">
        <sound-manager></sound-manager>
    </div>
</body>
</html>