@php
    use App\Http\Controllers\PartyFinderController;
    // ConvertEmptyStringsToNull後のnullを空文字に戻し、CSVでも「指定なし」を保つ。
    $exportQuery = array_map(static fn ($value) => $value ?? '', request()->query());
@endphp
@if ($entries !== null)
    <section class="mt-4" id="search-results" aria-labelledby="results-heading">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <div><p class="page-pretitle">SEARCH RESULTS</p><h2 id="results-heading">募集一覧</h2></div>
            @if (count($entries) > 0)
                <a class="btn" href="{{ route('partyfinder.export', $exportQuery) }}"><x-partyfinder.icon name="download" />CSVダウンロード</a>
            @endif
        </div>
        @if (count($entries) > 0)
            @include('partyfinder.partials.phase-matrix', ['matrixSummary' => $summary, 'matrixLabel' => '全コンテンツ', 'matrixOpen' => true])
        @endif
        <div class="card card-body mb-3">
            @include('partyfinder.partials.view-controls')
            <p class="text-secondary">変更後は「表示を適用」で再検索します。CSVは現在の検索結果を出力します。</p>
        </div>
        <p class="alert alert-warning" id="draft-notice" role="status" hidden>条件が変更されています。表示中の結果は前回の検索条件です。</p>
        <div class="summary d-flex flex-wrap gap-3 my-3">
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
            @if (($meta['excluded_by_phase'] ?? 0) > 0)
                <span>フェーズ条件で除外 {{ number_format($meta['excluded_by_phase']) }} 件</span>
            @endif
            @if (($meta['excluded_by_fixed'] ?? 0) > 0)
                <span>「固定」なしで除外 {{ number_format($meta['excluded_by_fixed']) }} 件</span>
            @endif
            @if (($meta['excluded_by_hidden'] ?? 0) > 0)
                <span>個別に除外 {{ number_format($meta['excluded_by_hidden']) }} 件</span>
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
                $statClass = ['タンク' => 'bg-blue-lt', 'ヒーラー' => 'bg-green-lt', 'DPS' => 'bg-red-lt'];
            @endphp
            <div class="d-flex flex-wrap align-items-center gap-2 my-3">
                <span class="head">募集枠の内訳</span>
                @foreach ($summary['groups'] as $group)
                    <span class="stat badge {{ $statClass[$group['key']] ?? '' }}">
                        {{ $group['label'] }}<b>{{ $summary['totals'][$group['key']] }}</b>
                    </span>
                    @foreach ($group['slots'] as $slot)
                        <span class="stat badge {{ $statClass[$group['key']] ?? '' }} {{ $summary['totals'][$slot['key']] === 0 ? 'zero' : '' }}">
                            {{ $slot['short'] }}<b>{{ $summary['totals'][$slot['key']] }}</b>
                        </span>
                    @endforeach
                @endforeach
                <span class="stat badge {{ $summary['totals'][$summary['unknown']['key']] === 0 ? 'zero' : '' }}">
                    {{ $summary['unknown']['short'] }}<b>{{ $summary['totals'][$summary['unknown']['key']] }}</b>
                </span>
            </div>
            @if (!empty($summary['jobs']))
                <div class="d-flex flex-wrap align-items-center gap-2 my-3">
                    <span class="head">ジョブ指定</span>
                    @foreach ($summary['jobs'] as $job => $count)
                        <span class="stat badge bg-secondary-lt">{{ $job }}<b>{{ $count }}</b></span>
                    @endforeach
                </div>
            @endif
        @endif

        @if (!empty($meta['failed_pages']))
            <div class="alert alert-warning" role="status">
                次のページは取得に失敗しました（時間をおいて再検索してください）：{{ implode(', ', $meta['failed_pages']) }} ページ
            </div>
        @endif

        <p class="hint mb-3">解析できない項目は「—」。募集期間・更新状況・詳しい条件は、募集日記でご確認ください。</p>

        @if (count($entries) === 0)
            <div class="empty card"><x-partyfinder.icon name="search" /><h3>条件に合う募集はありません</h3>
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
            @if (count($groups) > 1)
                <nav class="nav nav-pills flex-wrap gap-2 my-3" aria-label="コンテンツ別の募集へ移動">
                    @foreach ($groups as $i => $group)
                        <a class="nav-link" href="#content-{{ $i }}">{{ $group['label'] }} <b>{{ count($group['entries']) }}</b></a>
                    @endforeach
                </nav>
            @endif

            @foreach ($groups as $i => $group)
                <section class="group mt-4" id="content-{{ $i }}">
                    <h2>
                        <span class="badge bg-purple-lt">{{ $group['category'] }}</span>
                        {{ $group['label'] }}
                        <span class="text-secondary fw-normal">{{ count($group['entries']) }} 件</span>
                    </h2>

                    @include('partyfinder.partials.phase-matrix', ['matrixSummary' => $group['summary'], 'matrixLabel' => $group['label'], 'matrixOpen' => false])

                    @include('partyfinder.partials.recruitment-table')
                </section>
            @endforeach
        @else
            @include('partyfinder.partials.recruitment-cards')
        @endif
    </section>
@endif
