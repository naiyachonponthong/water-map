<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
@auth
    @php $reverb = \App\Support\Live::clientConfig(); @endphp
    @if($reverb)
        <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
        <script>window.REVERB = @json($reverb);</script>
    @endif
    <script src="{{ asset('js/live.js') }}?v={{ filemtime(public_path('js/live.js')) }}"></script>
@endauth
