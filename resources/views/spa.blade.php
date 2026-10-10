<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vivi - Sphere</title>
    <meta name="theme-color" content="#7A3956">
    {{-- Not indexed: this is the authenticated app, not a marketing page. --}}
    <meta name="robots" content="noindex">

    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%237A3956'/%3E%3Ctext x='16' y='22.5' font-family='Georgia,serif' font-size='19' font-weight='600' text-anchor='middle' fill='white'%3EV%3C/text%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:wght@500;600&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

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
