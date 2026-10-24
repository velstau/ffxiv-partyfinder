@php use App\Http\Controllers\PartyFinderController; @endphp
<div class="modal fade" id="saved-modal" tabindex="-1" aria-labelledby="saved-heading" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"><section class="modal-content" id="saved-panel" hidden>
        <div class="modal-header"><h2 class="modal-title" id="saved-heading"><x-partyfinder.icon name="bookmark" />保存した検索条件</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="閉じる"></button></div>
        <div class="modal-body">
            <div class="saved-form d-flex flex-wrap gap-2 align-items-end">
                <x-partyfinder.field name="saved-name" label="条件名"><input class="form-control" type="text" id="saved-name" maxlength="40" placeholder="空欄なら自動命名"></x-partyfinder.field>
                <x-partyfinder.button id="saved-save">現在の条件を保存</x-partyfinder.button>
            </div>
            <p class="text-secondary mt-2">入力中の条件を、このブラウザに保存します。</p>
            <div id="saved-notice" role="status" aria-live="polite"></div><ul class="saved-list list-group list-group-flush mt-3" id="saved-list"></ul>
        </div>
    </section></div>
</div>
<div class="modal fade" id="hidden-modal" tabindex="-1" aria-labelledby="hidden-heading" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"><section class="modal-content" id="hidden-panel" hidden>
        <div class="modal-header"><h2 class="modal-title" id="hidden-heading"><x-partyfinder.icon name="block" />除外した募集</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="閉じる"></button></div>
        <div class="modal-body">
            <p class="text-secondary">除外は検索結果・件数・CSVに反映されます。</p><div id="hidden-notice" role="status" aria-live="polite"></div>
            <p id="hidden-empty" class="text-secondary">除外した募集はありません。結果の「除外」から追加できます。</p>
            <details id="hidden-details" class="mt-3"><summary>除外した募集を確認・解除</summary><ul class="saved-list list-group list-group-flush mt-3" id="hidden-list"></ul></details>
        </div>
        <div class="modal-footer"><x-partyfinder.button id="hidden-clear">すべて解除</x-partyfinder.button><button class="btn ms-auto" type="button" data-bs-dismiss="modal">閉じる</button></div>
    </section></div>
</div>
<noscript><p class="alert alert-warning">検索はJavaScriptなしでも利用できます。検索条件の保存と募集の個別除外にはJavaScriptが必要です。</p></noscript>
