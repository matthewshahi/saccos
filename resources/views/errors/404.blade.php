<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}"> <!-- Assuming you have CSS assets -->
</head>
<body>
    <div class="container text-center">
        <h1 class="display-1">404</h1>
        <h2>Page Not Found</h2>
        <p>Sorry, the page you are looking for could not be found.</p>
        <a href="{{ route('home') }}" class="btn btn-primary">Go to Home</a>
        <a href="{{ route('login') }}" class="btn btn-secondary">Go to Login</a> <!-- Added login link -->
    </div>
</body>
</html>
