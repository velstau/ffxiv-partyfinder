<!DOCTYPE html>
<html lang="ja" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark">
    <title>FF14 固定・PT募集ファインダー</title>
    <link rel="stylesheet" href="{{ asset('vendor/tabler/1.7.1/tabler.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/partyfinder.css') }}?v={{ filemtime(public_path('css/partyfinder.css')) }}">
</head>
<body class="pf-app">
    <a class="pf-skip btn" href="#main-content">検索条件へ移動</a>
    <div class="page">
        <nav class="navbar navbar-expand-md" aria-label="サイト">
            <div class="container-xl gap-3">
                <a class="navbar-brand m-0" href="{{ route('partyfinder.index') }}"><x-partyfinder.icon name="grid" /><span class="ms-2">FF14 関連 <span class="text-secondary fw-normal">/ Party Finder</span></span></a>
                <a class="nav-link ms-auto" href="https://jp.finalfantasyxiv.com/lodestone/blog/" target="_blank" rel="noopener noreferrer">Lodestone <x-partyfinder.icon name="external" /><span class="visually-hidden">（新しいタブで開く）</span></a>
            </div>
        </nav>
        <div class="page-wrapper">
            <div class="container-xl">@yield('content')</div>
            @include('partyfinder.partials.footer')
        </div>
    </div>
    <script src="{{ asset('vendor/tabler/1.7.1/tabler.min.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
