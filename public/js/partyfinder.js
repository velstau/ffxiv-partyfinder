/* Party Finder: browser-local saved conditions and hidden entries. No build step. */
var partyFinderConfig = JSON.parse(document.getElementById('pf-config').textContent);

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
    var ROLE_FIELD  = 'role_filter[]';
    var PHASE_FIELD = 'phase_filter[]';

    // 一覧のサマリで「start」ではなく「最初から」と見せるため
    var PHASE_LABELS = partyFinderConfig.phaseLabels;

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
    document.getElementById('saved-open').hidden = false;
    document.getElementById('save-current-open').hidden = false;

    var noticeTimer = null;

    function notice(message, type) {
        noticeEl.textContent = message;
        noticeEl.className = type === 'error' ? 'text-danger mt-2' : 'text-success mt-2';
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
        params.role_filter  = checkedValues(ROLE_FIELD);
        params.phase_filter = checkedValues(PHASE_FIELD);
        return params;
    }

    /** チェックボックス群（募集ロール・フェーズ）の選択中の値 */
    function checkedValues(field) {
        var checked = form.querySelectorAll('input[name="' + field + '"]:checked');
        return Array.prototype.map.call(checked, function (el) { return el.value; });
    }

    /** 保存条件で検索を実行する（URLごと差し替えるのでサーバー側の結果と必ず一致する） */
    function runSearch(params) {
        var query = new URLSearchParams();
        query.set('search', '1');
        FIELDS.forEach(function (name) {
            var value = params[name];
            if (value === undefined || value === null) {
                return; // 古い保存条件に存在しない項目だけ省く
            }
            if (['match_tag', 'fixed_only', 'read_body'].indexOf(name) !== -1) {
                value = (value === '1' || value === 1 || value === true) ? '1' : '0';
            }
            query.set(name, value);
        });
        // 古い保存条件には role_filter・phase_filter が無いので、その場合は「すべて」になる
        (Array.isArray(params.role_filter) ? params.role_filter : []).forEach(function (role) {
            query.append(ROLE_FIELD, role);
        });
        (Array.isArray(params.phase_filter) ? params.phase_filter : []).forEach(function (phase) {
            query.append(PHASE_FIELD, phase);
        });
        window.dispatchEvent(new Event('pf:search'));
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
        if (params.match_tag === '1' || params.match_tag === 1 || params.match_tag === true)   { parts.push('タグも対象'); }
        if (params.fixed_only === '1' || params.fixed_only === 1 || params.fixed_only === true)  { parts.push('固定のみ'); }
        if (Array.isArray(params.role_filter) && params.role_filter.length) {
            parts.push('ロール: ' + params.role_filter.join('/'));
        }
        if (Array.isArray(params.phase_filter) && params.phase_filter.length) {
            parts.push('フェーズ: ' + params.phase_filter.map(function (key) {
                return PHASE_LABELS[key] || key;
            }).join('/'));
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
            empty.className = 'list-group-item text-secondary px-0';
            empty.textContent = '保存した条件はまだありません。条件を入力して「現在の条件を保存」を押してください。';
            listEl.appendChild(empty);
            return;
        }

        items.forEach(function (item) {
            var li = document.createElement('li');
            li.className = 'saved-item list-group-item d-flex gap-2 align-items-start px-0';

            var load = document.createElement('button');
            load.type = 'button';
            load.className = 'saved-load btn btn-ghost-secondary text-start flex-fill d-flex flex-column align-items-start';
            load.title = 'この条件で検索する';

            // 条件名はユーザー入力なので textContent で入れる（HTMLとして解釈させない）
            var name = document.createElement('span');
            name.className = 'fw-semibold';
            name.textContent = item.name;

            var desc = document.createElement('span');
            desc.className = 'text-secondary fw-normal';
            desc.textContent = summarize(item.params);

            load.appendChild(name);
            load.appendChild(desc);
            load.addEventListener('click', function () {
                runSearch(item.params);
            });

            var del = document.createElement('button');
            del.type = 'button';
            del.className = 'saved-del btn btn-icon flex-shrink-0';
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

/**
 * 募集の個別除外（最大 MAX_ITEMS 件。超えたら古いものから捨てる）。
 * 一覧（タイトルなど）は localStorage に持ち、日記IDだけを Cookie に書いてサーバーに渡す。
 * サーバー側で外すので、件数・内訳・CSV も除外後の内容になる。
 * 検索条件（URL）には入れないので、保存した条件やブックマークに関係なく効く。
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'ff_partyfinder.hidden_entries.v1';
    var FLASH_KEY   = STORAGE_KEY + '.flash';
    var MAX_ITEMS   = partyFinderConfig.maxHidden;
    var COOKIE_NAME = partyFinderConfig.hiddenCookie;
    // サブパス配下（/ff14tools/partyfinder）で配信するので、Cookie の path もそこに合わせる
    var COOKIE_PATH = partyFinderConfig.cookiePath;
    var COOKIE_DAYS = 365;
    var HAS_RESULTS = partyFinderConfig.hasResults;

    var panel     = document.getElementById('hidden-panel');
    var listEl    = document.getElementById('hidden-list');
    var countEl   = document.getElementById('hidden-count');
    var noticeEl  = document.getElementById('hidden-notice');
    var clearBtn  = document.getElementById('hidden-clear');
    var detailsEl = document.getElementById('hidden-details');
    var emptyEl   = document.getElementById('hidden-empty');

    // localStorage が使えない環境では機能ごと出さない（除外ボタンも hidden のまま）
    try {
        localStorage.setItem(STORAGE_KEY + '.probe', '1');
        localStorage.removeItem(STORAGE_KEY + '.probe');
    } catch (e) {
        return;
    }

    function notice(message, type) {
        noticeEl.textContent = message;
        noticeEl.className = type === 'error' ? 'text-danger mt-2' : 'text-success mt-2';
    }

    /** 除外一覧を読み出す（古い順）。壊れた値が入っていても落とさない。 */
    function read() {
        try {
            var parsed = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
            if (!Array.isArray(parsed)) {
                return [];
            }
            return parsed.filter(function (item) {
                return item && typeof item.id === 'string' && /^\d{1,20}$/.test(item.id);
            }).slice(-MAX_ITEMS);
        } catch (e) {
            return [];
        }
    }

    function write(items) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(items.slice(-MAX_ITEMS)));
        } catch (e) {
            notice('保存に失敗しました（ブラウザの保存領域がいっぱいの可能性があります）。', 'error');
            return false;
        }
        writeCookie(items.slice(-MAX_ITEMS));
        return true;
    }

    /** サーバーが読む Cookie を一覧から作り直す（区切りは Cookie で安全な「.」） */
    function writeCookie(items) {
        var value  = items.map(function (item) { return item.id; }).join('.');
        var maxAge = value === '' ? 0 : COOKIE_DAYS * 86400;
        document.cookie = COOKIE_NAME + '=' + value
            + '; path=' + COOKIE_PATH
            + '; max-age=' + maxAge
            + '; SameSite=Lax'
            + (location.protocol === 'https:' ? '; Secure' : '');
    }

    /** 次の表示で一度だけ出すお知らせ（除外・解除のあとに再読み込みするため） */
    function flash(message) {
        try {
            sessionStorage.setItem(FLASH_KEY, message);
        } catch (e) {
            // 出せなくても除外自体は済んでいる
        }
    }

    /** 検索結果を出している画面では、除外を反映させるため同じ条件で読み直す */
    function reloadOrRender(message) {
        if (HAS_RESULTS) {
            flash(message);
            window.dispatchEvent(new Event('pf:search'));
            window.location.reload();
            return;
        }
        render();
        notice(message, 'ok');
    }

    function render() {
        var items = read();

        countEl.textContent = items.length + ' / ' + MAX_ITEMS;
        countEl.classList.toggle('full', items.length >= MAX_ITEMS);
        clearBtn.disabled = items.length === 0;
        detailsEl.hidden  = items.length === 0;
        emptyEl.hidden    = items.length > 0;

        listEl.textContent = '';

        // 新しく除外したものを上に出す
        items.slice().reverse().forEach(function (item) {
            var li = document.createElement('li');
            li.className = 'saved-item list-group-item d-flex gap-2 align-items-start px-0';

            // タイトル等はロードストーン由来の文字列なので textContent で入れる
            var link = document.createElement('a');
            link.className = 'hidden-item-link flex-fill d-flex flex-column text-reset text-decoration-none';
            link.href = item.url || '#';
            link.target = '_blank';
            link.rel = 'noopener';

            var title = document.createElement('span');
            title.className = 'fw-semibold';
            title.textContent = item.title || ('日記 ' + item.id);

            var desc = document.createElement('span');
            desc.className = 'text-secondary';
            desc.textContent = item.chara || '';

            link.appendChild(title);
            link.appendChild(desc);

            var del = document.createElement('button');
            del.type = 'button';
            del.className = 'saved-del btn btn-icon flex-shrink-0';
            del.textContent = '×';
            del.title = '除外を解除';
            del.setAttribute('aria-label', (item.title || item.id) + ' の除外を解除');
            del.addEventListener('click', function () {
                var rest = read().filter(function (hidden) {
                    return hidden.id !== item.id;
                });
                if (write(rest)) {
                    reloadOrRender('「' + (item.title || item.id) + '」の除外を解除しました。');
                }
            });

            li.appendChild(link);
            li.appendChild(del);
            listEl.appendChild(li);
        });
    }

    clearBtn.addEventListener('click', function () {
        var count = read().length;
        if (count === 0 || !window.confirm('除外した ' + count + ' 件をすべて解除しますか？')) {
            return;
        }
        if (write([])) {
            reloadOrRender('除外をすべて解除しました。');
        }
    });

    // 除外ボタン（表・カードの両方）
    Array.prototype.forEach.call(document.querySelectorAll('.hide-btn[data-hide-id]'), function (btn) {
        btn.hidden = false;
        btn.addEventListener('click', function () {
            var id    = btn.getAttribute('data-hide-id');
            var items = read().filter(function (item) {
                return item.id !== id;
            });
            items.push({
                id:      id,
                title:   btn.getAttribute('data-hide-title') || '',
                chara:   btn.getAttribute('data-hide-chara') || '',
                url:     btn.getAttribute('data-hide-url') || '',
                addedAt: Date.now()
            });
            if (!write(items)) {
                return;
            }
            btn.disabled = true;
            reloadOrRender('「' + (btn.getAttribute('data-hide-title') || id) + '」を除外しました。「除外した募集」から元に戻せます。');
        });
    });

    // Cookie の期限を延ばし、localStorage と食い違っていたら揃える
    writeCookie(read());

    panel.hidden = false;
    document.getElementById('hidden-open').hidden = false;
    render();

    try {
        var message = sessionStorage.getItem(FLASH_KEY);
        if (message) {
            sessionStorage.removeItem(FLASH_KEY);
            notice(message, 'ok');
            var pageNotice = document.getElementById('personal-notice');
            pageNotice.textContent = message;
            pageNotice.hidden = false;
        }
    } catch (e) {
        // お知らせが出せないだけ
    }
})();

/** Shared form state. Native GET submission remains the source of truth. */
(function () {
    'use strict';

    var form = document.getElementById('search-form');
    var status = document.getElementById('search-status');
    var draft = document.getElementById('draft-notice');
    var busy = false;
    var buttons = Array.prototype.filter.call(form.elements, function (el) {
        return el.tagName === 'BUTTON' && el.type === 'submit';
    });
    function snapshot() {
        return new URLSearchParams(new FormData(form)).toString();
    }
    var appliedInputs = snapshot();
    function showDraft() {
        if (draft) {
            draft.hidden = snapshot() === appliedInputs;
        }
    }
    function startLoading() {
        busy = true;
        form.setAttribute('aria-busy', 'true');
        status.textContent = 'ロードストーンから募集を取得しています…';
        buttons.forEach(function (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        });
    }
    function resetLoading() {
        busy = false;
        form.removeAttribute('aria-busy');
        status.textContent = '';
        buttons.forEach(function (button) {
            button.disabled = false;
            button.removeAttribute('aria-busy');
        });
        showDraft();
    }
    form.addEventListener('submit', function (event) {
        if (busy) {
            event.preventDefault();
            return;
        }
        startLoading();
    });
    // Includes controls associated through form="search-form" outside the form.
    ['input', 'change'].forEach(function (eventName) {
        document.addEventListener(eventName, function (event) {
            if (event.target.form === form) {
                showDraft();
            }
        });
    });
    window.addEventListener('pf:search', startLoading);
    window.addEventListener('pageshow', resetLoading);

    var errors = document.getElementById('validation-errors');
    if (errors) {
        errors.focus();
    }
})();
