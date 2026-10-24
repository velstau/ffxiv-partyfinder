@php
    use App\Http\Controllers\PartyFinderController;
    use App\Services\LodestoneBlogService;
    $roleTones = ['タンク' => 'tank', 'ヒーラー' => 'heal', 'DPS' => 'dps'];
@endphp
<form class="search card" id="search-form" method="GET" action="{{ route('partyfinder.index') }}">
    <input type="hidden" name="search" value="1">
    <div class="card-header d-flex justify-content-between gap-3">
        <div>
            <h2 class="card-title"><x-partyfinder.icon name="search" />検索条件</h2>
            <p class="text-secondary">条件を組み合わせて、探している募集へ。</p>
        </div>
        <x-partyfinder.button variant="primary" type="submit" icon="search" class="primary">検索する</x-partyfinder.button>
    </div>

    <div class="card-body">
        <div class="field-grid">
            <x-partyfinder.field name="q" label="ロードストーン検索キーワード" class="field-span-2" hint="ロードストーン側に渡す検索語。全角スペース区切りでAND検索されます。">
                <input class="form-control" type="text" id="q" name="q" value="{{ $conditions['q'] }}" placeholder="零式　固定　募集" aria-describedby="q-hint">
            </x-partyfinder.field>
            <x-partyfinder.field name="worldname" label="ホームワールド / DC" hint="空欄ですべて。DCは _dc_Meteor のように指定。">
                <input class="form-control" type="text" id="worldname" name="worldname" value="{{ $conditions['worldname'] }}" placeholder="例: Ramuh" aria-describedby="worldname-hint">
            </x-partyfinder.field>
            <x-partyfinder.field name="keywords" label="絞り込みキーワード（AND）" hint="スペース・カンマ・読点区切り。すべてを含むタイトルに絞ります。">
                <input class="form-control" type="text" id="keywords" name="keywords" value="{{ $conditions['keywords'] }}" placeholder="絶 妖星 D3" aria-describedby="keywords-hint">
            </x-partyfinder.field>
            <x-partyfinder.field name="ng_keywords" label="除外キーワード（NG）" :hint="'1つでも含むタイトルを除外。' . (config('ff14.title_ng_words') ? '「' . implode('」「', (array) config('ff14.title_ng_words')) . '」は常に除外します。' : '')">
                <input class="form-control" type="text" id="ng_keywords" name="ng_keywords" value="{{ $conditions['ng_keywords'] }}" placeholder="初見 練習" aria-describedby="ng_keywords-hint">
            </x-partyfinder.field>
            <x-partyfinder.field name="within_days" label="投稿期間で絞り込む" hint="日時不明の日記は、期間を指定しても除外しません。">
                <select class="form-select" id="within_days" name="within_days" aria-describedby="within_days-hint">
                    @foreach (PartyFinderController::PERIOD_OPTIONS as $value => $label)
                        <option value="{{ $value }}" @selected($conditions['within_days'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-partyfinder.field>
        </div>

        <fieldset class="pf-filter-group" aria-describedby="role-hint">
            <legend><x-partyfinder.icon name="shield" />募集ロール <span class="text-secondary fw-normal ms-2">複数選択可・いずれかに一致</span></legend>
            <div class="form-selectgroup">
                @foreach (config('ff14.role_filters') as $key => $filter)
                    <x-partyfinder.choice-chip name="role_filter[]" :value="$key" :checked="in_array($key, $conditions['role_filter'], true)" :tone="$roleTones[$filter['group']] ?? null">{{ $filter['label'] }}</x-partyfinder.choice-chip>
                @endforeach
            </div>
            <p class="text-secondary" id="role-hint">未選択ならすべて。「D3」はレンジ・吟遊詩人、「D1」は近接・侍などの表記も含みます。</p>
        </fieldset>

        <fieldset class="pf-filter-group" aria-describedby="phase-hint">
            <legend><x-partyfinder.icon name="flag" />進行フェーズ <span class="text-secondary fw-normal ms-2">複数選択可・いずれかに一致</span></legend>
            <div class="form-selectgroup">
                @foreach (config('ff14.phase_filters') as $key => $label)
                    <x-partyfinder.choice-chip name="phase_filter[]" :value="$key" :checked="in_array($key, $conditions['phase_filter'], true)">{{ $label }}</x-partyfinder.choice-chip>
                @endforeach
            </div>
            <p class="text-secondary" id="phase-hint">未選択ならすべて。「P5最初〜」「P5以降」はP5。「不明」を選ばない場合、本文未確認を含めフェーズ不明の日記は出ません。</p>
        </fieldset>

        <div class="d-flex flex-wrap gap-3 mt-3">
            <label class="form-check d-flex align-items-center gap-2 mb-0"><input class="form-check-input m-0" type="checkbox" name="fixed_only" value="1" @checked($conditions['fixed_only'])>タイトルに「固定」を含むものだけ</label>
            <label class="form-check d-flex align-items-center gap-2 mb-0"><input class="form-check-input m-0" type="checkbox" name="match_tag" value="1" @checked($conditions['match_tag'])>絞り込みの対象にタグも含める</label>
            <label class="form-check d-flex align-items-center gap-2 mb-0"><input class="form-check-input m-0" type="checkbox" name="read_body" value="1" @checked($conditions['read_body'])>フェーズ不明の記事は本文も読む（遅い）</label>
        </div>

        <details class="pf-advanced border-top mt-3" id="advanced-filters" @if ($errors->any() || $conditions['blog_lang'] !== 'ja' || $conditions['order'] !== '' || $conditions['page_from'] !== 1 || $conditions['page_to'] !== 10) open @endif>
            <summary class="py-3"><x-partyfinder.icon name="sliders" /><span>詳細設定</span><span class="text-secondary ms-2">言語・取得順・走査ページ</span></summary>
            <div class="field-grid">
                <x-partyfinder.field name="blog_lang" label="日記の言語">
                    <select class="form-select" id="blog_lang" name="blog_lang">
                        @foreach (PartyFinderController::LANG_OPTIONS as $value => $label)
                            <option value="{{ $value }}" @selected($conditions['blog_lang'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-partyfinder.field>
                <x-partyfinder.field name="order" label="ロードストーンの並び順" hint="取得するページに影響します。結果の並び替えとは別の設定です。">
                    <select class="form-select" id="order" name="order" aria-describedby="order-hint">
                        @foreach (PartyFinderController::ORDER_OPTIONS as $value => $label)
                            <option value="{{ $value }}" @selected($conditions['order'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-partyfinder.field>
                <fieldset class="pf-page-range" aria-describedby="pages-hint">
                    <legend>走査ページ範囲（最大 {{ LodestoneBlogService::MAX_PAGE }}）</legend>
                    <div class="pages">
                        <label class="visually-hidden" for="page_from">開始ページ</label>
                        <input class="form-control" type="number" id="page_from" name="page_from" min="1" max="{{ LodestoneBlogService::MAX_PAGE }}" value="{{ $conditions['page_from'] }}">
                        <span aria-hidden="true">〜</span>
                        <label class="visually-hidden" for="page_to">終了ページ</label>
                        <input class="form-control" type="number" id="page_to" name="page_to" min="1" max="{{ LodestoneBlogService::MAX_PAGE }}" value="{{ $conditions['page_to'] }}">
                    </div>
                    <p class="text-secondary" id="pages-hint">1ページ50件。範囲を広げるほど時間がかかります。</p>
                </fieldset>
            </div>
        </details>
    </div>
    <div class="card-footer">
        @if ($entries === null)
            @include('partyfinder.partials.view-controls')
        @endif
        <div class="d-flex flex-wrap align-items-center gap-2">
            <x-partyfinder.button variant="primary" type="submit" icon="search" class="primary">検索する</x-partyfinder.button>
            <a class="btn btn-ghost-secondary" href="{{ route('partyfinder.index') }}">条件をリセット</a>
            <button class="btn btn-ghost-secondary ms-sm-auto" id="save-current-open" type="button" data-bs-toggle="modal" data-bs-target="#saved-modal" hidden><x-partyfinder.icon name="bookmark" />現在の条件を保存</button>
            <p class="text-secondary" id="search-status" role="status" aria-live="polite"></p>
        </div>
    </div>
</form>
