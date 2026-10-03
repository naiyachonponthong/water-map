    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
        window.DISPATCH = {
            suggestUrl: @json(url('/admin/cases/__ID__/suggest')),
            respondUrl: @json(url('/admin/assignments/__ID__/respond')),
            progressUrl: @json(url('/admin/assignments/__ID__/progress')),
            completeUrl: @json(url('/admin/assignments/__ID__/complete')),
            cancelUrl: @json(url('/admin/assignments/__ID__/cancel')),
            provinceId: {{ current_province()?->id ?? 0 }},
        };
    </script>
    <script src="{{ asset('js/dispatch.js') }}?v={{ filemtime(public_path('js/dispatch.js')) }}"></script>
