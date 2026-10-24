<div class="results">
    @foreach ($entries as $entry)
        <article class="entry card card-body">
            @php $a = $entry['analysis']; @endphp
            @if ($entry['face_url'])
                <img class="face avatar" src="{{ $entry['face_url'] }}" alt="" loading="lazy" referrerpolicy="no-referrer">
            @endif

            <div class="body">
                <a class="title" href="{{ $entry['url'] }}" target="_blank" rel="noopener">{{ $entry['title'] }}<x-partyfinder.icon name="external" /><span class="visually-hidden">（募集日記を新しいタブで開く）</span></a>
                <div class="pf-card-analysis">
                    <span class="badge bg-purple-lt">{{ $a['content']['label'] }}</span>
                    @if ($a['is_fixed'])<span class="flag fixed badge bg-green-lt">固定</span>@endif
                    <div class="pf-card-fact"><span class="text-secondary">フェーズ</span><x-partyfinder.phase :analysis="$a" /></div>
                    <div class="pf-card-fact"><span class="text-secondary">募集ロール</span><x-partyfinder.roles :analysis="$a" /></div>
                    @if ($a['open_slots'])<span class="chip badge bg-secondary-lt">空き枠 {{ '@' . $a['open_slots'] }}</span>@endif
                </div>
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
                            <span class="tag badge bg-secondary-lt">{{ $tag }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="side">
                @if ($entry['posted_at'])
                    @php $postedAt = \Carbon\Carbon::createFromTimestamp($entry['posted_at'])->timezone(config('app.timezone')); @endphp
                    <span class="text-secondary">投稿日時</span>
                    <span class="date">{{ $postedAt->format('Y-m-d H:i') }}</span>
                    <span class="rel">{{ $postedAt->diffForHumans() }}</span>
                @endif
                <div class="counts"><x-partyfinder.icon name="message-circle" />コメント {{ $entry['comment'] }} ／ <x-partyfinder.icon name="thumb-up" />いいね {{ $entry['like'] }}</div>
                <x-partyfinder.hide-button :entry="$entry" />
            </div>
        </article>
    @endforeach
</div>
