<article class="admin-activity-item">
    <span class="admin-activity-marker" aria-hidden="true">@include('admin.partials.icon', ['name' => $activity->action === 'user.created' ? 'add' : 'history'])</span>
    <div class="admin-activity-body">
        <div class="admin-activity-heading"><span class="admin-event-badge" data-action="{{ $activity->action }}">{{ \App\Models\AdminActivityLog::ACTIONS[$activity->action] ?? $activity->action }}</span><time datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->timezone('Asia/Jakarta')->format('d M Y, H.i') }} WIB</time></div>
        <p><strong>{{ $activity->actor_name }}</strong> {{ $activity->action === 'user.created' ? 'menambahkan akun' : 'memperbarui akun' }} <strong>{{ $activity->subject_name }}</strong>.</p>
        @if($detailed ?? false)
        <details class="admin-change-details"><summary>Lihat perubahan <span aria-hidden="true">+</span></summary>
            <dl>
                @foreach(\App\Models\AdminActivityLog::FIELDS as $field => $label)
                    @if(isset($activity->changes[$field]))
                    <div><dt>{{ $label }}</dt><dd>@if($activity->action !== 'user.created')<span class="admin-change-before">{{ $activity->displayValue($field, $activity->changes[$field]['before']) }}</span><span aria-label="menjadi"> → </span>@endif<span>{{ $activity->displayValue($field, $activity->changes[$field]['after']) }}</span></dd></div>
                    @endif
                @endforeach
                @if($activity->changes['password_changed'] ?? false)<div><dt>Kata sandi</dt><dd>{{ $activity->action === 'user.created' ? 'Kata sandi akun ditetapkan.' : 'Kata sandi diperbarui.' }}</dd></div>@endif
            </dl>
        </details>
        @endif
    </div>
</article>
