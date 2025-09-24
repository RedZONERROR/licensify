<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
</head>
<body>
    <div class="container">
        <h1>Welcome to {{ config('app.name') }}</h1>
        
        @auth
            <p>Welcome back, {{ auth()->user()->name }}!</p>
            <a href="{{ route('dashboard') }}">Go to Dashboard</a>
        @else
            <div>
                <a href="{{ route('login') }}">Login</a>
                <a href="{{ route('register') }}">Register</a>
            </div>
        @endauth
    </div>
</body>
</html>