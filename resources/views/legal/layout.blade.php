<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | IDireksyon</title>
    <style>
        body { margin: 0; background: #f3f8ff; color: #1f2d3d; font: 16px/1.65 system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
        main { max-width: 720px; margin: 0 auto; padding: 32px 20px 56px; }
        article { background: #fff; border: 1px solid #e3ecf7; border-radius: 16px; padding: 28px 24px; }
        .brand { margin: 0 0 16px; font-weight: 700; color: #0b4295; }
        h1 { margin: 0 0 4px; font-size: 30px; line-height: 1.2; color: #103765; }
        h2 { margin: 30px 0 8px; font-size: 19px; line-height: 1.3; color: #103765; }
        p { margin: 0 0 12px; }
        ul, ol { margin: 0 0 12px; padding-left: 22px; }
        li { margin-bottom: 6px; }
        a { color: #0b4295; }
        .updated { color: #5b6b7f; font-size: 14px; }
        .notice { margin: 20px 0 0; padding: 14px 16px; background: #e7eef8; border-radius: 12px; color: #263c5c; }
        footer { margin-top: 20px; color: #5b6b7f; font-size: 14px; text-align: center; }
    </style>
</head>
<body>
    <main>
        <p class="brand">IDireksyon by GeoBytes</p>
        <article>
            @yield('content')
        </article>
        <footer>
            <a href="{{ route('privacy', [], false) }}">Privacy Policy</a> ·
            <a href="{{ route('account-deletion', [], false) }}">Account Deletion</a>
        </footer>
    </main>
</body>
</html>
