<ol class="location-stepper" aria-label="Tahapan pengajuan">
    <li class="{{ $step === 'lokasi' ? 'is-current' : 'is-complete' }}" @if($step === 'lokasi') aria-current="step" @endif>
        @if($step === 'lokasi')
            <span><span aria-hidden="true">1</span>Data lokasi</span>
        @else
            <a href="{{ route('pengajuan.lokasi') }}"><span aria-hidden="true">&#10003;</span>Data lokasi</a>
        @endif
    </li>
    <li class="{{ $step === 'bukti' ? 'is-current' : ($step === 'ringkasan' ? 'is-complete' : 'is-pending') }}" @if($step === 'bukti') aria-current="step" @endif>
        @if($step === 'ringkasan')
            <a href="{{ route('pengajuan.bukti') }}"><span aria-hidden="true">&#10003;</span>Bukti foto</a>
        @else
            <span><span aria-hidden="true">2</span>Bukti foto</span>
        @endif
    </li>
    <li class="{{ $step === 'ringkasan' ? 'is-current' : 'is-pending' }}" @if($step === 'ringkasan') aria-current="step" @endif><span><span aria-hidden="true">3</span>Ringkasan &amp; kirim</span></li>
</ol>
