@php
    use App\Http\Controllers\PartyFinderController;
    use App\Services\LodestoneBlogService;
@endphp
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FF14 固定・PT募集ファインダー</title>
    <style>
        :root {
            --bg:      #0f1419;
            --panel:   #1a2230;
            --panel-2: #222d3d;
            --line:    #2e3b4f;
            --text:    #e6edf3;
            --muted:   #8b9cb3;
            --accent:  #4fb8ff;
            --ok:      #3fcf7a;
            --warn:    #ffbf47;
            --err:     #ff5f6e;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: radial-gradient(1200px 600px at 80% -10%, #1c2942 0%, var(--bg) 55%);
            color: var(--text);
            font-family: -apple-system, "Segoe UI", "Hiragino Kaku Gothic ProN", "Noto Sans JP", Meiryo, sans-serif;
            font-size: 1rem;
            line-height: 1.6;
            min-height: 100vh;
        }
        a { color: var(--accent); }
        .wrap { max-width: 1080px; margin: 0 auto; padding: 2.2rem 1.2rem 4rem; }

        header { margin-bottom: 1.6rem; }
        h1 { font-size: 1.7rem; margin: 0 0 .3rem; letter-spacing: .02em; }
        h1 .badge {
            font-size: .9rem; font-weight: 600; color: var(--accent);
            border: 1px solid var(--accent); border-radius: 999px;
            padding: .1rem .7rem; margin-left: .6rem; vertical-align: middle;
        }
        .lead { color: var(--muted); font-size: .95rem; margin: 0; }
        .lead a { color: var(--muted); }

        /* ── 検索フォーム ────────────────────────── */
        form.search {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 1.3rem;
        }
        .field-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem 1.2rem;
        }
        .field { display: flex; flex-direction: column; gap: .35rem; }
        .field.wide { grid-column: 1 / -1; }
        label { font-size: .9rem; color: var(--muted); }
        label .req { color: var(--accent); }
        .hint { font-size: .8rem; color: var(--muted); opacity: .85; }
        input[type="text"], input[type="number"], select {
            background: var(--panel-2);
            border: 1px solid var(--line);
            border-radius: 8px;
            color: var(--text);
            padding: .55rem .7rem;
            font-size: 1rem;
            font-family: inherit;
            width: 100%;
        }
        input:focus, select:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
        .pages { display: flex; align-items: center; gap: .5rem; }
        .pages input { width: 5.5rem; }
        .checkline { display: flex; align-items: center; gap: .5rem; color: var(--muted); font-size: .9rem; }
        .checkline input { accent-color: var(--accent); width: 1rem; height: 1rem; }

        .actions {
            display: flex; align-items: center; gap: .8rem;
            margin-top: 1.2rem; flex-wrap: wrap;
        }
        button.primary {
            background: var(--accent);
            border: none; border-radius: 8px;
            color: #06121d; font-weight: 700; font-size: 1rem;
            padding: .6rem 1.6rem; cursor: pointer;
        }
        button.primary:hover { filter: brightness(1.1); }
        .btn-ghost {
            border: 1px solid var(--line); border-radius: 8px;
            color: var(--muted); text-decoration: none;
            padding: .55rem 1.1rem; font-size: .9rem;
        }
        .btn-ghost:hover { border-color: var(--accent); color: var(--accent); }

        /* ── 結果サマリ ─────────────────────────── */
        .summary {
            display: flex; flex-wrap: wrap; gap: .4rem 1.4rem;
            margin: 1.8rem 0 .4rem; color: var(--muted); font-size: .92rem;
            align-items: center;
        }
        .summary b { color: var(--text); font-size: 1.05rem; }
        .summary .spacer { margin-left: auto; }

        .notice {
            border-radius: 10px; padding: .8rem 1rem; margin-top: 1.4rem;
            border: 1px solid var(--line); background: var(--panel);
            font-size: .93rem;
        }
        .notice.error { border-color: var(--err); color: #ffd7da; }
        .notice.warn  { border-color: var(--warn); color: #ffeec4; }

        /* ── 結果一覧 ───────────────────────────── */
        .results { display: flex; flex-direction: column; gap: .8rem; margin-top: 1rem; }
        .entry {
            display: flex; gap: 1rem;
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 1rem 1.1rem;
        }
        .entry:hover { border-color: var(--accent); background: var(--panel-2); }
        .entry .face {
            width: 52px; height: 52px; border-radius: 50%;
            object-fit: cover; flex-shrink: 0; background: var(--panel-2);
        }
        .entry .body { min-width: 0; flex: 1; }
        .entry .title {
            font-size: 1.08rem; font-weight: 700;
            text-decoration: none; color: var(--text);
            display: block; margin-bottom: .3rem; word-break: break-word;
        }
        .entry .title:hover { color: var(--accent); text-decoration: underline; }
        .entry .who { font-size: .9rem; color: var(--muted); }
        .entry .who .world { color: var(--text); }
        .entry .tags { margin-top: .45rem; display: flex; flex-wrap: wrap; gap: .35rem; }
        .entry .tag {
            font-size: .78rem; color: var(--accent);
            background: var(--panel-2); border: 1px solid var(--line);
            border-radius: 5px; padding: .05rem .5rem;
        }
        .entry .side {
            text-align: right; font-size: .82rem; color: var(--muted);
            white-space: nowrap; flex-shrink: 0;
        }
        .entry .side .date { color: var(--text); font-variant-numeric: tabular-nums; }
        .entry .side .rel { display: block; }
        .entry .side .counts { margin-top: .3rem; }

        .empty { color: var(--muted); text-align: center; padding: 3rem 1rem; }

        /* ── 募集ロールの絞り込み ───────────────── */
        .role-filter { display: flex; flex-wrap: wrap; gap: .35rem .5rem; }
        .role-filter label {
            display: inline-flex; align-items: center; gap: .35rem; cursor: pointer;
            border: 1px solid var(--line); background: var(--panel-2);
            border-radius: 999px; padding: .2rem .8rem;
            font-size: .87rem; color: var(--text);
        }
        .role-filter label:hover { border-color: var(--accent); }
        .role-filter label:has(:checked) { border-color: var(--accent); color: var(--accent); }
        .role-filter input { accent-color: var(--accent); width: .95rem; height: .95rem; margin: 0; }

        /* ── 内訳・集計 ─────────────────────────── */
        .breakdown {
            display: flex; flex-wrap: wrap; align-items: baseline; gap: .3rem .5rem;
            margin: .6rem 0 0; padding: .7rem .9rem;
            background: var(--panel); border: 1px solid var(--line); border-radius: 12px;
        }
        .breakdown .head { font-size: .85rem; color: var(--muted); margin-right: .2rem; }
        .breakdown .stat {
            font-size: .85rem; border: 1px solid var(--line); border-radius: 999px;
            padding: .1rem .65rem; white-space: nowrap;
        }
        .breakdown .stat b { font-variant-numeric: tabular-nums; margin-left: .25rem; }
        .breakdown .stat.zero { color: var(--muted); opacity: .55; }
        .breakdown .stat.lead { font-weight: 700; background: var(--panel-2); }
        .breakdown .stat.tank { color: #8ab4ff; border-color: #3b5a99; }
        .breakdown .stat.heal { color: #7fe0a5; border-color: #2f6b47; }
        .breakdown .stat.dps  { color: #ff9a9a; border-color: #8c3b40; }

        details.matrix { margin: 0 0 .6rem; }
        details.matrix > summary {
            cursor: pointer; font-size: .87rem; color: var(--muted);
            padding: .2rem 0; list-style-position: inside;
        }
        details.matrix > summary:hover { color: var(--accent); }
        table.matrix-table { border-collapse: collapse; font-size: .87rem; min-width: 0; }
        table.matrix-table th, table.matrix-table td {
            padding: .3rem .7rem; border-bottom: 1px solid var(--line); text-align: right;
            font-variant-numeric: tabular-nums; white-space: nowrap;
        }
        table.matrix-table thead th { background: var(--panel-2); color: var(--muted); font-size: .8rem; }
        table.matrix-table th.ph { text-align: left; color: var(--text); font-weight: 600; }
        table.matrix-table td.zero { color: var(--muted); opacity: .35; }
        table.matrix-table tfoot th, table.matrix-table tfoot td {
            border-bottom: none; border-top: 1px solid var(--line); color: var(--muted);
        }
        table.matrix-table .total { color: var(--text); font-weight: 600; }
        table.matrix-table .grp { background: var(--panel-2); }
        table.matrix-table thead th.grp { color: var(--text); }
        .matrix-note { font-size: .78rem; color: var(--muted); margin: .4rem 0 0; }
        .jobs { font-size: .85rem; color: var(--muted); margin: .5rem 0 0; }

        /* ── コンテンツ別の表 ───────────────────── */
        .toc { display: flex; flex-wrap: wrap; gap: .4rem; margin: 1rem 0 .2rem; }
        .toc a {
            font-size: .85rem; text-decoration: none; color: var(--muted);
            border: 1px solid var(--line); background: var(--panel);
            border-radius: 999px; padding: .15rem .75rem;
        }
        .toc a:hover { border-color: var(--accent); color: var(--accent); }

        .group { margin-top: 1.7rem; }
        .group h2 {
            font-size: 1.05rem; margin: 0 0 .5rem;
            display: flex; align-items: center; gap: .5rem; flex-wrap: wrap;
        }
        .group .cat {
            font-size: .75rem; color: var(--accent);
            border: 1px solid var(--accent); border-radius: 4px; padding: 0 .4rem;
        }
        .group .count { font-size: .85rem; color: var(--muted); font-weight: 400; }

        .table-scroll {
            overflow-x: auto;
            border: 1px solid var(--line); border-radius: 12px; background: var(--panel);
        }
        table.recruits { border-collapse: collapse; width: 100%; font-size: .9rem; min-width: 780px; }
        table.recruits th, table.recruits td {
            padding: .5rem .7rem; text-align: left; vertical-align: top;
            border-bottom: 1px solid var(--line);
        }
        table.recruits thead th {
            background: var(--panel-2); color: var(--muted);
            font-weight: 600; font-size: .82rem; white-space: nowrap;
        }
        table.recruits tbody tr:last-child td { border-bottom: none; }
        table.recruits tbody tr:hover { background: var(--panel-2); }
        td.phase, td.slots, td.when { white-space: nowrap; }
        td.slots { text-align: right; font-variant-numeric: tabular-nums; }
        td.when { font-variant-numeric: tabular-nums; font-size: .82rem; color: var(--muted); }
        td.title a { color: var(--text); text-decoration: none; font-weight: 600; }
        td.title a:hover { color: var(--accent); text-decoration: underline; }
        td.who { font-size: .84rem; }
        .none { color: var(--muted); }

        .chip {
            display: inline-block; font-size: .78rem; border-radius: 5px;
            padding: .02rem .45rem; margin: .05rem .2rem .05rem 0;
            border: 1px solid var(--line); white-space: nowrap;
        }
        .chip.tank  { color: #8ab4ff; border-color: #3b5a99; }
        .chip.heal  { color: #7fe0a5; border-color: #2f6b47; }
        .chip.dps   { color: #ff9a9a; border-color: #8c3b40; }
        .chip.job   { color: var(--text); }
        .chip.other { color: var(--muted); }
        .chip.ng    { color: var(--muted); text-decoration: line-through; }

        .flag {
            font-size: .72rem; border-radius: 4px; padding: 0 .35rem;
            border: 1px solid var(--line); color: var(--muted); margin-right: .3rem;
            white-space: nowrap;
        }
        .flag.fixed { color: var(--ok); border-color: var(--ok); }
        .flag.body {
            color: var(--warn); border-color: var(--warn);
            margin: 0 0 0 .3rem; font-size: .68rem;
        }

        /* ── 保存した検索条件（localStorage） ──────── */
        .saved {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 1rem 1.1rem;
            margin-top: 1rem;
        }
        .saved-head {
            display: flex; align-items: center; gap: .6rem 1rem;
            flex-wrap: wrap; margin-bottom: .8rem;
        }
        .saved-head h2 {
            font-size: 1rem; margin: 0; font-weight: 700;
        }
        .saved-count {
            font-size: .82rem; color: var(--muted);
            border: 1px solid var(--line); border-radius: 999px;
            padding: .05rem .6rem; font-variant-numeric: tabular-nums;
        }
        .saved-count.full { color: var(--warn); border-color: var(--warn); }
        .saved-form { display: flex; gap: .5rem; margin-left: auto; flex-wrap: wrap; }
        .saved-form input { width: 15rem; padding: .4rem .6rem; font-size: .9rem; }
        .saved-form button {
            background: var(--panel-2); border: 1px solid var(--line);
            border-radius: 8px; color: var(--text);
            padding: .4rem 1rem; font-size: .9rem; cursor: pointer; font-family: inherit;
        }
        .saved-form button:hover { border-color: var(--accent); color: var(--accent); }

        #saved-notice { font-size: .88rem; margin-bottom: .6rem; }
        #saved-notice.ok    { color: var(--ok); }
        #saved-notice.error { color: var(--err); }
        #saved-notice:empty { display: none; }

        .saved-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .4rem; }
        .saved-item { display: flex; align-items: stretch; gap: .4rem; }
        .saved-load {
            flex: 1; min-width: 0; text-align: left; cursor: pointer;
            background: var(--panel-2); border: 1px solid var(--line);
            border-radius: 8px; padding: .5rem .8rem;
            color: var(--text); font-family: inherit; font-size: .95rem;
            display: flex; flex-direction: column; gap: .1rem;
        }
        .saved-load:hover { border-color: var(--accent); }
        .saved-item-name { font-weight: 700; }
        .saved-item-desc {
            font-size: .8rem; color: var(--muted);
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .saved-del {
            background: none; border: 1px solid var(--line); border-radius: 8px;
            color: var(--muted); cursor: pointer; font-size: 1.1rem;
            width: 2.4rem; flex-shrink: 0; font-family: inherit;
        }
        .saved-del:hover { border-color: var(--err); color: var(--err); }
        .saved-empty { color: var(--muted); font-size: .9rem; }

        footer {
            margin-top: 2.6rem; color: var(--muted); font-size: .88rem;
            border-top: 1px solid var(--line); padding-top: 1rem;
        }
        code {
            background: var(--panel-2); padding: .1rem .4rem;
            border-radius: 5px; font-size: .88em;
        }
        @media (max-width: 560px) {
            .entry { flex-wrap: wrap; }
            .entry .side { text-align: left; width: 100%; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <h1>FF14 固定・PT募集ファインダー<span class="badge">Lodestone</span></h1>
        <p class="lead">
            ロードストーンの日記検索を横断して、タイトルに指定キーワードを<strong>すべて</strong>含む募集日記だけを抜き出します。
        </p>
    </header>

    <form class="search" method="GET" action="{{ route('partyfinder.index') }}">
        <input type="hidden" name="search" value="1">

        <div class="field-grid">
            <div class="field wide">
                <label for="q">ロードストーン検索キーワード <span class="req">*</span></label>
                <input type="text" id="q" name="q" value="{{ $conditions['q'] }}" placeholder="零式　固定　募集">
                <span class="hint">ロードストーン側に渡す検索語。全角スペース区切りでAND検索されます。</span>
            </div>

            <div class="field">
                <label for="keywords">絞り込みキーワード（AND）</label>
                <input type="text" id="keywords" name="keywords" value="{{ $conditions['keywords'] }}" placeholder="絶 妖星 D3">
                <span class="hint">スペース・カンマ・読点区切り。すべて含むタイトルだけ残します。</span>
            </div>

            <div class="field">
                <label for="ng_keywords">除外キーワード（NG）</label>
                <input type="text" id="ng_keywords" name="ng_keywords" value="{{ $conditions['ng_keywords'] }}" placeholder="初見 練習">
                <span class="hint">1つでも含まれていたら結果から除外します。</span>
            </div>

            <div class="field">
                <label for="worldname">ホームワールド / DC</label>
                <input type="text" id="worldname" name="worldname" value="{{ $conditions['worldname'] }}" placeholder="例: Ramuh">
                <span class="hint">空欄ですべて。DCで絞るときは <code>_dc_Meteor</code> のように指定します。</span>
            </div>

            <div class="field">
                <label for="blog_lang">日記の言語</label>
                <select id="blog_lang" name="blog_lang">
                    @foreach (PartyFinderController::LANG_OPTIONS as $value => $label)
                        <option value="{{ $value }}" @selected($conditions['blog_lang'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="order">ロードストーンの並び順</label>
                <select id="order" name="order">
                    @foreach (PartyFinderController::ORDER_OPTIONS as $value => $label)
                        <option value="{{ $value }}" @selected($conditions['order'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <span class="hint">どのページを走査するかに影響します。</span>
            </div>

            <div class="field">
                <label for="page_from">走査ページ範囲（最大 {{ LodestoneBlogService::MAX_PAGE }}）</label>
                <div class="pages">
                    <input type="number" id="page_from" name="page_from" min="1" max="{{ LodestoneBlogService::MAX_PAGE }}" value="{{ $conditions['page_from'] }}">
                    <span>〜</span>
                    <input type="number" id="page_to" name="page_to" min="1" max="{{ LodestoneBlogService::MAX_PAGE }}" value="{{ $conditions['page_to'] }}">
                </div>
                <span class="hint">1ページ50件。範囲を広げるほど時間がかかります。</span>
            </div>

            <div class="field">
                <label for="within_days">投稿期間で絞り込む</label>
                <select id="within_days" name="within_days">
                    @foreach (PartyFinderController::PERIOD_OPTIONS as $value => $label)
                        <option value="{{ $value }}" @selected($conditions['within_days'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <span class="hint">古い募集を除外します。投稿日時が読めなかった日記は除外しません。</span>
            </div>

            <div class="field">
                <label for="sort">結果の並び替え</label>
                <select id="sort" name="sort">
                    @foreach (PartyFinderController::SORT_OPTIONS as $value => $label)
                        <option value="{{ $value }}" @selected($conditions['sort'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <label class="checkline" style="margin-top:.5rem">
                    <input type="checkbox" name="match_tag" value="1" @checked($conditions['match_tag'])>
                    絞り込みの対象にタグも含める
                </label>
            </div>

            <div class="field">
                <label for="view_mode">結果の見せ方</label>
                <select id="view_mode" name="view_mode">
                    @foreach (PartyFinderController::VIEW_OPTIONS as $value => $label)
                        <option value="{{ $value }}" @selected($conditions['view_mode'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <label class="checkline" style="margin-top:.5rem">
                    <input type="checkbox" name="fixed_only" value="1" @checked($conditions['fixed_only'])>
                    タイトルに「固定」を含むものだけ
                </label>
                <label class="checkline">
                    <input type="checkbox" name="read_body" value="1" @checked($conditions['read_body'])>
                    フェーズ不明の記事は本文も読む（遅い）
                </label>
            </div>

            <div class="field wide">
                <label>募集ロールで絞り込む</label>
                <div class="role-filter">
                    @foreach (config('ff14.role_filters') as $key => $filter)
                        <label>
                            <input type="checkbox" name="role_filter[]" value="{{ $key }}"
                                   @checked(in_array($key, $conditions['role_filter'], true))>
                            {{ $filter['label'] }}
                        </label>
                    @endforeach
                </div>
                <span class="hint">
                    選んだ枠のどれかを募集している日記だけ残します（何も選ばなければすべて）。
                    「D3」は「レンジ」「吟遊詩人」などの書き方も、「D1」は「近接」「侍」なども拾います。
                </span>
            </div>
        </div>

        <div class="actions">
            <button type="submit" class="primary">検索する</button>
            @if ($entries !== null && count($entries) > 0)
                <a class="btn-ghost" href="{{ route('partyfinder.export', request()->query()) }}">CSVダウンロード</a>
            @endif
            <a class="btn-ghost" href="{{ route('partyfinder.index') }}">条件をリセット</a>
        </div>
    </form>

    {{-- 保存した検索条件。サーバーには送らず、ブラウザのlocalStorageだけで完結させる。 --}}
    <section class="saved" id="saved-panel" hidden>
        <div class="saved-head">
            <h2>保存した検索条件</h2>
            <span class="saved-count" id="saved-count">0 / 10</span>
            <div class="saved-form">
                <input type="text" id="saved-name" maxlength="40" placeholder="条件名（空欄なら自動命名）">
                <button type="button" id="saved-save">現在の条件を保存</button>
            </div>
        </div>
        <div id="saved-notice" role="status"></div>
        <ul class="saved-list" id="saved-list"></ul>
    </section>

    @if ($errors->any())
        <div class="notice error">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    @if ($error)
        <div class="notice error">{{ $error }}</div>
    @endif

    @if ($entries !== null)
        <div class="summary">
            <span>該当 <b>{{ number_format(count($entries)) }}</b> 件</span>
            <span>走査 {{ number_format($meta['scanned_count']) }} 件 / {{ $meta['scanned_pages'] }} ページ</span>
            @if (($meta['phase_from_body'] ?? 0) > 0)
                <span>本文からフェーズ判定 {{ number_format($meta['phase_from_body']) }} 件</span>
            @endif
            @if (($meta['body_not_read'] ?? 0) > 0)
                <span>本文未確認 {{ number_format($meta['body_not_read']) }} 件（上限）</span>
            @endif
            @if (($meta['excluded_by_role'] ?? 0) > 0)
                <span>ロール条件で除外 {{ number_format($meta['excluded_by_role']) }} 件</span>
            @endif
            @if (($meta['excluded_by_fixed'] ?? 0) > 0)
                <span>「固定」なしで除外 {{ number_format($meta['excluded_by_fixed']) }} 件</span>
            @endif
            @if ($meta['excluded_by_period'] > 0)
                <span>期間外で除外 {{ number_format($meta['excluded_by_period']) }} 件（{{ PartyFinderController::PERIOD_OPTIONS[$conditions['within_days']] ?? '' }}）</span>
            @endif
            @if ($meta['total_hits'] !== null)
                <span>ロードストーン総ヒット {{ number_format($meta['total_hits']) }} 件（全 {{ $meta['total_pages'] }} ページ）</span>
            @endif
            <span class="spacer">所要 {{ $elapsed }} 秒</span>
        </div>

        @if (count($entries) > 0)
            @php
                // 枠の色分けはロールチップと合わせる
                $statClass = ['タンク' => 'tank', 'ヒーラー' => 'heal', 'DPS' => 'dps'];
            @endphp
            <div class="breakdown">
                <span class="head">募集枠の内訳</span>
                @foreach ($summary['groups'] as $group)
                    <span class="stat lead {{ $statClass[$group['key']] ?? '' }}">
                        {{ $group['label'] }}<b>{{ $summary['totals'][$group['key']] }}</b>
                    </span>
                    @foreach ($group['slots'] as $slot)
                        <span class="stat {{ $statClass[$group['key']] ?? '' }} {{ $summary['totals'][$slot['key']] === 0 ? 'zero' : '' }}">
                            {{ $slot['short'] }}<b>{{ $summary['totals'][$slot['key']] }}</b>
                        </span>
                    @endforeach
                @endforeach
                <span class="stat {{ $summary['totals'][$summary['unknown']['key']] === 0 ? 'zero' : '' }}">
                    {{ $summary['unknown']['short'] }}<b>{{ $summary['totals'][$summary['unknown']['key']] }}</b>
                </span>
            </div>
            @if (!empty($summary['jobs']))
                <div class="breakdown">
                    <span class="head">ジョブ指定</span>
                    @foreach ($summary['jobs'] as $job => $count)
                        <span class="stat">{{ $job }}<b>{{ $count }}</b></span>
                    @endforeach
                </div>
            @endif
        @endif

        @if (!empty($meta['failed_pages']))
            <div class="notice warn">
                次のページは取得に失敗しました（時間をおいて再検索してください）：{{ implode(', ', $meta['failed_pages']) }} ページ
            </div>
        @endif

        @if (count($entries) === 0)
            <div class="empty">
                条件に一致する日記は見つかりませんでした。<br>
                絞り込みキーワードを減らすか、走査ページ範囲を広げてみてください。
                @if ($conditions['fixed_only'])
                    <br>「タイトルに『固定』を含むものだけ」のチェックを外すと増えることがあります。
                @endif
                @if ($conditions['role_filter'] !== [])
                    <br>ロールをタイトルに書いていない募集も多いので、「不明（タイトルに記載なし）」も
                    一緒に選ぶと拾えることがあります。
                @endif
            </div>
        @elseif ($conditions['view_mode'] === 'table')
            @php
                // ロールのグループごとに色を変えて、表を見たときに枠が読み取れるようにする
                $roleClass = ['タンク' => 'tank', 'ヒーラー' => 'heal', 'DPS' => 'dps', 'ジョブ' => 'job'];
            @endphp

            @if (count($groups) > 1)
                <nav class="toc">
                    @foreach ($groups as $i => $group)
                        <a href="#content-{{ $i }}">{{ $group['label'] }} <b>{{ count($group['entries']) }}</b></a>
                    @endforeach
                </nav>
            @endif

            @foreach ($groups as $i => $group)
                <section class="group" id="content-{{ $i }}">
                    <h2>
                        <span class="cat">{{ $group['category'] }}</span>
                        {{ $group['label'] }}
                        <span class="count">{{ count($group['entries']) }} 件</span>
                    </h2>

                    @if (count($group['entries']) >= 2)
                        @php $sum = $group['summary']; @endphp
                        <details class="matrix" open>
                            <summary>フェーズ×募集枠の件数</summary>
                            <div class="table-scroll">
                                <table class="matrix-table">
                                    <thead>
                                        <tr>
                                            <th class="ph" rowspan="2">フェーズ</th>
                                            @foreach ($sum['groups'] as $g)
                                                <th colspan="{{ count($g['slots']) + 1 }}" class="grp">{{ $g['label'] }}</th>
                                            @endforeach
                                            <th rowspan="2">{{ $sum['unknown']['short'] }}</th>
                                            <th rowspan="2" class="total">件数</th>
                                        </tr>
                                        <tr>
                                            @foreach ($sum['groups'] as $g)
                                                <th class="grp">計</th>
                                                @foreach ($g['slots'] as $slot)
                                                    <th>{{ $slot['short'] }}</th>
                                                @endforeach
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($sum['phases'] as $row)
                                            <tr>
                                                <th class="ph">{{ $row['label'] }}</th>
                                                @foreach ($sum['groups'] as $g)
                                                    <td class="grp {{ $row['counts'][$g['key']] === 0 ? 'zero' : '' }}">{{ $row['counts'][$g['key']] }}</td>
                                                    @foreach ($g['slots'] as $slot)
                                                        <td class="{{ $row['counts'][$slot['key']] === 0 ? 'zero' : '' }}">{{ $row['counts'][$slot['key']] }}</td>
                                                    @endforeach
                                                @endforeach
                                                <td class="{{ $row['counts'][$sum['unknown']['key']] === 0 ? 'zero' : '' }}">{{ $row['counts'][$sum['unknown']['key']] }}</td>
                                                <td class="total">{{ $row['total'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th class="ph">合計</th>
                                            @foreach ($sum['groups'] as $g)
                                                <td class="grp">{{ $sum['totals'][$g['key']] }}</td>
                                                @foreach ($g['slots'] as $slot)
                                                    <td>{{ $sum['totals'][$slot['key']] }}</td>
                                                @endforeach
                                            @endforeach
                                            <td>{{ $sum['totals'][$sum['unknown']['key']] }}</td>
                                            <td class="total">{{ $sum['count'] }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <p class="matrix-note">
                                「タンク」「DPS」の列は MT＋ST の足し算ではなく、どちらかを募集していれば1件。
                                「DPS募集」「なんでも」のような総称は該当する枠すべてに数えるので、枠ごとの数字の合計は件数と一致しない。
                            </p>
                            @if (!empty($sum['jobs']))
                                <p class="jobs">
                                    ジョブ指定：
                                    @foreach ($sum['jobs'] as $job => $count)
                                        <span class="chip job">{{ $job }} {{ $count }}</span>
                                    @endforeach
                                </p>
                            @endif
                        </details>
                    @endif

                    <div class="table-scroll">
                        <table class="recruits">
                            <thead>
                                <tr>
                                    <th>フェーズ</th>
                                    <th>募集ロール</th>
                                    <th>枠</th>
                                    <th>タイトル</th>
                                    <th>投稿者 / ワールド</th>
                                    <th>投稿日時</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($group['entries'] as $entry)
                                    @php $a = $entry['analysis']; @endphp
                                    <tr>
                                        <td class="phase">
                                            @if ($a['phase'])
                                                <span @if ($a['phase']['raw'] && $a['phase']['raw'] !== $a['phase']['label']) title="{{ $a['phase_source'] === 'body' ? '本文' : 'タイトル' }}の表記：{{ $a['phase']['raw'] }}" @endif>
                                                    {{ $a['phase']['label'] }}
                                                </span>
                                                @if ($a['phase_source'] === 'body')
                                                    <span class="flag body" title="タイトルには書かれておらず、本文から判定したもの">本文</span>
                                                @endif
                                            @else
                                                <span class="none">—</span>
                                            @endif
                                        </td>

                                        <td class="roles">
                                            @forelse ($a['roles'] as $role)
                                                <span class="chip {{ $roleClass[$role['group']] ?? 'other' }}">{{ $role['label'] }}</span>
                                            @empty
                                                @if (empty($a['excluded_roles']))
                                                    <span class="none">—</span>
                                                @endif
                                            @endforelse
                                            @foreach ($a['excluded_roles'] as $role)
                                                <span class="chip ng">{{ $role['label'] }}</span>
                                            @endforeach
                                        </td>

                                        <td class="slots">
                                            @if ($a['open_slots'])
                                                {{ '@' . $a['open_slots'] }}
                                            @else
                                                <span class="none">—</span>
                                            @endif
                                        </td>

                                        <td class="title">
                                            @if ($a['is_fixed'])
                                                <span class="flag fixed">固定</span>
                                            @endif
                                            @if ($a['style'] !== '攻略')
                                                <span class="flag">{{ $a['style'] }}</span>
                                            @endif
                                            <a href="{{ $entry['url'] }}" target="_blank" rel="noopener">{{ $entry['title'] }}</a>
                                        </td>

                                        <td class="who">
                                            @if ($entry['chara_url'])
                                                <a href="{{ $entry['chara_url'] }}" target="_blank" rel="noopener">{{ $entry['chara_name'] }}</a>
                                            @else
                                                {{ $entry['chara_name'] }}
                                            @endif
                                            <span class="none">／ {{ $entry['chara_world'] }}</span>
                                        </td>

                                        <td class="when">
                                            @if ($entry['posted_at'])
                                                {{ \Carbon\Carbon::createFromTimestamp($entry['posted_at'])->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                                            @else
                                                <span class="none">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        @else
            <div class="results">
                @foreach ($entries as $entry)
                    <article class="entry">
                        @if ($entry['face_url'])
                            <img class="face" src="{{ $entry['face_url'] }}" alt="" loading="lazy" referrerpolicy="no-referrer">
                        @endif

                        <div class="body">
                            <a class="title" href="{{ $entry['url'] }}" target="_blank" rel="noopener">{{ $entry['title'] }}</a>
                            <div class="who">
                                @if ($entry['chara_url'])
                                    <a href="{{ $entry['chara_url'] }}" target="_blank" rel="noopener">{{ $entry['chara_name'] }}</a>
                                @else
                                    {{ $entry['chara_name'] }}
                                @endif
                                <span class="world">／ {{ $entry['chara_world'] }}</span>
                            </div>
                            @if (!empty($entry['tags']))
                                <div class="tags">
                                    @foreach ($entry['tags'] as $tag)
                                        <span class="tag">{{ $tag }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="side">
                            @if ($entry['posted_at'])
                                @php $postedAt = \Carbon\Carbon::createFromTimestamp($entry['posted_at'])->timezone(config('app.timezone')); @endphp
                                <span class="date">{{ $postedAt->format('Y-m-d H:i') }}</span>
                                <span class="rel">{{ $postedAt->diffForHumans() }}</span>
                            @endif
                            <div class="counts">💬 {{ $entry['comment'] }} ／ 👍 {{ $entry['like'] }}</div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    @endif

    <footer>
        取得結果は5分間キャッシュされます。元データ：
        <a href="https://jp.finalfantasyxiv.com/lodestone/blog/" target="_blank" rel="noopener">ロードストーン 日記検索</a>
        （FF_PARTYFINDER.py の Web 版）
    </footer>
</div>

<script>
/**
 * 検索条件の保存（最大10件）。
 * サーバーには保存せず localStorage のみで完結させるため、端末・ブラウザごとの保存になる。
 * 上限に達したら自動削除はせず、明示的に削除してもらう。
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'ff_partyfinder.saved_conditions.v1';
    var MAX_ITEMS   = 10;

    // フォームの input[name] と一致させる。保存対象の全項目。
    var FIELDS = ['q', 'keywords', 'ng_keywords', 'worldname',
                  'blog_lang', 'order', 'page_from', 'page_to', 'within_days', 'sort',
                  'match_tag', 'view_mode', 'fixed_only', 'read_body'];

    // 複数選べる項目（チェックボックス群）は配列で持つ
    var ROLE_FIELD = 'role_filter[]';

    var form     = document.querySelector('form.search');
    var panel    = document.getElementById('saved-panel');
    var listEl   = document.getElementById('saved-list');
    var countEl  = document.getElementById('saved-count');
    var noticeEl = document.getElementById('saved-notice');
    var nameEl   = document.getElementById('saved-name');
    var saveBtn  = document.getElementById('saved-save');

    // localStorage が使えない環境（プライベートモード等）では機能ごと出さない
    try {
        localStorage.setItem(STORAGE_KEY + '.probe', '1');
        localStorage.removeItem(STORAGE_KEY + '.probe');
    } catch (e) {
        return;
    }
    panel.hidden = false;

    var noticeTimer = null;

    function notice(message, type) {
        noticeEl.textContent = message;
        noticeEl.className = type || '';
        clearTimeout(noticeTimer);
        if (type === 'ok') {
            noticeTimer = setTimeout(function () {
                noticeEl.textContent = '';
                noticeEl.className = '';
            }, 4000);
        }
    }

    /** 保存済み条件を読み出す。壊れた値が入っていても落とさない。 */
    function read() {
        try {
            var parsed = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
            if (!Array.isArray(parsed)) {
                return [];
            }
            return parsed.filter(function (item) {
                return item && typeof item.name === 'string' && item.params && typeof item.params === 'object';
            }).slice(0, MAX_ITEMS);
        } catch (e) {
            return [];
        }
    }

    function write(items) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
            return true;
        } catch (e) {
            notice('保存に失敗しました（ブラウザの保存領域がいっぱいの可能性があります）。', 'error');
            return false;
        }
    }

    /** 現在のフォーム内容を取り出す */
    function currentParams() {
        var params = {};
        FIELDS.forEach(function (name) {
            var el = form.elements[name];
            if (!el) {
                return;
            }
            params[name] = (el.type === 'checkbox') ? (el.checked ? '1' : '') : el.value;
        });
        params.role_filter = checkedRoles();
        return params;
    }

    /** 選択中の募集ロール条件 */
    function checkedRoles() {
        var checked = form.querySelectorAll('input[name="' + ROLE_FIELD + '"]:checked');
        return Array.prototype.map.call(checked, function (el) { return el.value; });
    }

    /** 保存条件で検索を実行する（URLごと差し替えるのでサーバー側の結果と必ず一致する） */
    function runSearch(params) {
        var query = new URLSearchParams();
        query.set('search', '1');
        FIELDS.forEach(function (name) {
            var value = params[name];
            if (value === undefined || value === null || value === '') {
                return; // 空欄は「指定なし」として送らない
            }
            query.set(name, value);
        });
        // 古い保存条件には role_filter が無いので、その場合は「すべて」になる
        (params.role_filter || []).forEach(function (role) {
            query.append(ROLE_FIELD, role);
        });
        window.location.search = query.toString();
    }

    /** 一覧に出す1行サマリ */
    function summarize(params) {
        var parts = [];
        if (params.q)           { parts.push('検索: ' + params.q); }
        if (params.keywords)    { parts.push('絞込: ' + params.keywords); }
        if (params.ng_keywords) { parts.push('除外: ' + params.ng_keywords); }
        if (params.worldname)   { parts.push('World: ' + params.worldname); }
        if (params.within_days) { parts.push(params.within_days + '日以内'); }
        if (params.match_tag)   { parts.push('タグも対象'); }
        if (params.fixed_only)  { parts.push('固定のみ'); }
        if (params.role_filter && params.role_filter.length) {
            parts.push('ロール: ' + params.role_filter.join('/'));
        }
        parts.push('P' + (params.page_from || 1) + '〜' + (params.page_to || 1));
        return parts.join(' / ');
    }

    function defaultName(params) {
        var base = params.keywords || params.q || '無題の条件';
        return base.slice(0, 40);
    }

    function render() {
        var items = read();

        countEl.textContent = items.length + ' / ' + MAX_ITEMS;
        countEl.classList.toggle('full', items.length >= MAX_ITEMS);

        listEl.textContent = '';

        if (items.length === 0) {
            var empty = document.createElement('li');
            empty.className = 'saved-empty';
            empty.textContent = '保存した条件はまだありません。条件を入力して「現在の条件を保存」を押してください。';
            listEl.appendChild(empty);
            return;
        }

        items.forEach(function (item) {
            var li = document.createElement('li');
            li.className = 'saved-item';

            var load = document.createElement('button');
            load.type = 'button';
            load.className = 'saved-load';
            load.title = 'この条件で検索する';

            // 条件名はユーザー入力なので textContent で入れる（HTMLとして解釈させない）
            var name = document.createElement('span');
            name.className = 'saved-item-name';
            name.textContent = item.name;

            var desc = document.createElement('span');
            desc.className = 'saved-item-desc';
            desc.textContent = summarize(item.params);

            load.appendChild(name);
            load.appendChild(desc);
            load.addEventListener('click', function () {
                runSearch(item.params);
            });

            var del = document.createElement('button');
            del.type = 'button';
            del.className = 'saved-del';
            del.textContent = '×';
            del.title = '削除';
            del.setAttribute('aria-label', item.name + ' を削除');
            del.addEventListener('click', function () {
                var rest = read().filter(function (saved) {
                    return saved.id !== item.id;
                });
                if (write(rest)) {
                    render();
                    notice('「' + item.name + '」を削除しました。', 'ok');
                }
            });

            li.appendChild(load);
            li.appendChild(del);
            listEl.appendChild(li);
        });
    }

    saveBtn.addEventListener('click', function () {
        var items  = read();
        var params = currentParams();
        var name   = (nameEl.value || '').trim() || defaultName(params);

        // 同名は上書き（上限には引っかからない）
        var index = items.findIndex(function (item) {
            return item.name === name;
        });

        if (index >= 0) {
            items[index].params  = params;
            items[index].savedAt = Date.now();
            if (write(items)) {
                nameEl.value = '';
                render();
                notice('「' + name + '」を上書き保存しました。', 'ok');
            }
            return;
        }

        if (items.length >= MAX_ITEMS) {
            notice('保存できるのは ' + MAX_ITEMS + ' 件までです。不要な条件を削除してから保存してください。', 'error');
            return;
        }

        items.push({
            id:      'c' + Date.now() + Math.random().toString(36).slice(2, 8),
            name:    name,
            params:  params,
            savedAt: Date.now()
        });

        if (write(items)) {
            nameEl.value = '';
            render();
            notice('「' + name + '」を保存しました。', 'ok');
        }
    });

    // 条件名の入力中に Enter で保存できるようにする（フォーム送信は起こさない）
    nameEl.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            saveBtn.click();
        }
    });

    render();
})();
</script>
</body>
</html>
