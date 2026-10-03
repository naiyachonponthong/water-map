<div class="flash-wrap" aria-live="polite">
    @if(session('success'))
        <div class="flash success" data-autohide>
            <i class="bi bi-check-circle-fill"></i>
            <div class="flex-grow-1">{{ session('success') }}</div>
            <button type="button" class="btn-close btn-sm" aria-label="ปิด"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="flash error">
            <i class="bi bi-exclamation-circle-fill"></i>
            <div class="flex-grow-1">{{ session('error') }}</div>
            <button type="button" class="btn-close btn-sm" aria-label="ปิด"></button>
        </div>
    @endif
    @if($errors->any() && ! old('_modal'))
        <div class="flash error">
            <i class="bi bi-exclamation-circle-fill"></i>
            <div class="flex-grow-1">
                @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
            <button type="button" class="btn-close btn-sm" aria-label="ปิด"></button>
        </div>
    @endif
</div>
