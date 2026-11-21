<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') - {{ $instanceName }}</title>
    <style>
        :root { color-scheme: light dark; --accent: #2f6f5e; --error: #b3261e; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; line-height: 1.5; background: Canvas; color: CanvasText; }
        main { max-width: 32rem; margin: 0 auto; padding: 2rem 1.25rem; }
        h1 { font-size: 1.6rem; line-height: 1.25; margin: 0 0 .75rem; }
        code { display: block; padding: .6rem .75rem; border: 1px solid GrayText; border-radius: .4rem; font-size: 1rem; overflow-wrap: anywhere; user-select: all; }
        ol { padding-left: 1.25rem; }
        li { margin: .5rem 0; }
        label { display: block; font-weight: 600; margin-top: 1rem; }
        input { box-sizing: border-box; width: 100%; padding: .7rem .75rem; margin-top: .25rem; border: 1px solid GrayText; border-radius: .4rem; font: inherit; background: Field; color: FieldText; }
        input[aria-invalid="true"] { border: 2px solid var(--error); }
        input:focus-visible, button:focus-visible { outline: 3px solid var(--accent); outline-offset: 2px; }
        .hint { font-size: .9rem; margin: .25rem 0 0; }
        .error { color: var(--error); font-weight: 600; margin: .25rem 0 0; }
        @media (prefers-color-scheme: dark) { :root { --error: #f2b8b5; } }
        .alert { border: 2px solid var(--error); border-radius: .6rem; padding: .75rem 1rem; margin: 1rem 0; }
        button { display: block; width: 100%; padding: .9rem 1rem; margin: 1.5rem 0; border: 0; border-radius: .6rem; background: var(--accent); color: #fff; font: inherit; font-size: 1.1rem; font-weight: 600; cursor: pointer; }
    </style>
</head>
<body>
<main>
@yield('content')
</main>
</body>
</html>
