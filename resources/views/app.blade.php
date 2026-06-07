<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <title>SocPlay — Live Football Scores</title>
    <meta name="description" content="Live football scores, fixtures, results, standings, and brackets.">

    <link rel="icon" href="/favicon.ico" sizes="any">

    @vite(['resources/css/app.css', 'resources/js/main.js'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
