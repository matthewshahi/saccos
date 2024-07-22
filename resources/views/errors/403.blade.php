<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied</title>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-danger">
            <h1>Access Denied</h1>
            <p>You do not have permission to access this page.</p>
        </div>
        <a href="{{ url('/') }}" class="btn btn-primary">Go to Home</a>
    </div>
</body>
</html>
