@extends('layouts.partyfinder')

@section('content')
    <header class="page-header mb-4">
        <div class="row align-items-center g-3">
            <div class="col"><div class="page-pretitle">PARTY FINDER</div><h1 class="page-title">固定・PT募集ファインダー</h1><p class="text-secondary mt-2">ロールと進行度から、あなたに合う募集を。</p></div>
            <div class="col-auto"><div class="btn-list">
                <button class="btn" type="button" data-bs-toggle="modal" data-bs-target="#saved-modal" id="saved-open" hidden><x-partyfinder.icon name="bookmark" />保存条件 <span class="badge bg-blue-lt" id="saved-count">0 / 10</span></button>
                <button class="btn" type="button" data-bs-toggle="modal" data-bs-target="#hidden-modal" id="hidden-open" hidden><x-partyfinder.icon name="block" />除外リスト <span class="badge bg-secondary-lt" id="hidden-count">0 / 300</span></button>
            </div></div>
        </div>
    </header>
    <main id="main-content" tabindex="-1">
        <p class="alert alert-info" id="personal-notice" role="status" hidden></p>
        @if ($errors->any())
            <div class="alert alert-danger" role="alert" tabindex="-1" id="validation-errors">
                <h2>検索条件を確認してください</h2>
                <ul>@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
            </div>
        @endif
        @if ($error)
            <div class="alert alert-danger" role="alert"><h2>検索を完了できませんでした</h2><p>{{ $error }}</p></div>
        @endif

        @include('partyfinder.partials.search-form')
        @include('partyfinder.partials.personal-tools')
        @include('partyfinder.partials.results')

        @if ($entries === null && !$error && !$errors->any())
            <section class="empty my-4" aria-labelledby="initial-heading">
                <x-partyfinder.icon name="search" />
                <h2 id="initial-heading">次のパーティを探しましょう</h2>
                <p>条件を選んで「検索する」を押すと、ここに募集が表示されます。</p>
                <p class="text-secondary">解析できない項目は「—」で表示します。募集の詳細は元の日記でご確認ください。</p>
            </section>
        @endif
    </main>
@endsection

@push('scripts')
    @php
    $clientConfig = [
        'phaseLabels' => config('ff14.phase_filters'),
        'maxHidden' => \App\Http\Controllers\PartyFinderController::MAX_HIDDEN,
        'hiddenCookie' => \App\Http\Controllers\PartyFinderController::HIDDEN_COOKIE,
        'cookiePath' => parse_url(route('partyfinder.index'), PHP_URL_PATH) ?: '/',
        'hasResults' => $entries !== null,
    ];
    @endphp
    <script type="application/json" id="pf-config">@json($clientConfig)</script>
    <script src="{{ asset('js/partyfinder.js') }}?v={{ filemtime(public_path('js/partyfinder.js')) }}" defer></script>
@endpush
