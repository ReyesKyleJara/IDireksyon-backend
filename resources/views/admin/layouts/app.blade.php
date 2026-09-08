<!DOCTYPE html>
<html>
<head>
    <title>IDireksyon Admin</title>
</head>
<body>

    <h1>IDireksyon Admin Panel</h1>

<nav>
    <a href="{{ route('admin.dashboard') }}">
        Dashboard
    </a>

    <a href="{{ route('admin.government-ids.index') }}">
        Government IDs
    </a>
</nav>

    <hr>

    @yield('content')

</body>
</html>