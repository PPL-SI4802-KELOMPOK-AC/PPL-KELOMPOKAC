@if($errors->any())
    <div class="notice is-error" role="alert">
        <strong>Pengajuan belum lengkap:</strong>
        <ul>
            @foreach($errors->getMessages() as $field => $messages)
                @php
                    $target = match (true) {
                        str_starts_with($field, 'photos') => route('pengajuan.bukti').'#evidence-title',
                        $field === 'pernyataan' => route('pengajuan.ringkasan').'#pernyataan',
                        default => route('pengajuan.lokasi').'#location-fields-title',
                    };
                @endphp
                <li><a href="{{ $target }}">{{ $messages[0] }}</a></li>
            @endforeach
        </ul>
    </div>
@endif
