<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('app.name', 'Shared Inbox') }}</title>

    {{--
        This is the Inertia root view for the shared inbox pages, published
        (or referenced in place via the "shared-inbox::app" view namespace)
        so it renders inside the HOST app's own build. The host app is
        expected to already run Vite + Inertia + React (see BUILD_PROMPT.md
        and README.md "Frontend integration") — this view assumes a host
        entry point at resources/js/app.jsx that resolves pages from both
        the host app and this package (see the useSharedInboxChannel /
        Inertia resolve() docs in README.md). Swap the @vite entry below if
        the host app's entry lives elsewhere.
    --}}
    @viteReactRefresh
    @vite(['resources/js/app.jsx'])
    @inertiaHead
</head>
<body class="antialiased">
    @inertia
</body>
</html>
