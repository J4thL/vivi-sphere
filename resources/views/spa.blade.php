@extends('layouts.app')

@section('title', ($salonSettings->salon_name ?? 'Company Name') . ' — Book online')

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- Not indexed: this is the authenticated app, not a marketing page. --}}
    <meta name="robots" content="noindex">

    <script>
        // Points the dashboard's fetch calls at this same Laravel app's API.
        window.API_BASE = "{{ url('/api') }}";
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body>
    <div id="root"></div>
</body>
</html>
