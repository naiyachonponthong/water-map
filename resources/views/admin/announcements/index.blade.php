@extends('layouts.app')
@section('title', 'ประกาศและแจ้งเตือน')

@section('content')
    @use('App\Support\ReliefOptions')

    <x-page-head title="ประกาศและแจ้งเตือน" sub="ประกาศถึงประชาชนบนเว็บและ LINE OA หรือส่งถึงเจ้าหน้าที่ที่ผูก LINE ไว้">
        @unless($lineReady)
            @can('settings.manage')<a href="{{ route('admin.settings.index', ['tab' => 'line']) }}" class="btn btn-soft"><i class="bi bi-chat-dots me-1"></i>ตั้งค่า LINE OA</a>@endcan
        @endunless
    </x-page-head>

    <div class="row g-3">
        <div class="col-xl-5">
            <form method="POST" action="{{ route('announcements.store') }}" class="card">
                @csrf
                <input type="hidden" name="alert_id" value="{{ old('alert_id', $draft['alert_id'] ?? '') }}">
                <div class="card-header"><i class="bi bi-megaphone text-primary"></i> เขียนประกาศ</div>
                <div class="card-body">
                    @if($errors->any())<div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle"></i><div>{{ $errors->first() }}</div></div>@endif
                    <div class="d-flex gap-2 mb-3">
                        @foreach(ReliefOptions::ANNOUNCE_LEVELS as $k => [$l, , $color])
                            <input type="radio" class="btn-check" name="level" value="{{ $k }}" id="lv{{ $k }}" @checked(old('level', $draft['level'] ?? 'info') === $k)>
                            <label class="btn btn-outline-secondary flex-fill" for="lv{{ $k }}" style="--bs-btn-active-bg:{{ $color }};--bs-btn-active-border-color:{{ $color }}">{{ $l }}</label>
                        @endforeach
                    </div>
                    <input name="title" class="form-control mb-2" maxlength="200" placeholder="หัวข้อ" value="{{ old('title', $draft['title'] ?? '') }}" required>
                    <textarea name="body" class="form-control mb-1" rows="6" maxlength="4000" placeholder="ข้อความ" id="annBody" required>{{ old('body', $draft['body'] ?? '') }}</textarea>
                    <div class="form-text text-end mb-2"><span id="annCount">0</span>/4000</div>
                    <select name="district_ids[]" class="form-select mb-2" multiple data-search data-placeholder="พื้นที่ (เว้นว่าง = ทั้งจังหวัด)">
                        @foreach($districts as $d)<option value="{{ $d->id }}" @selected(in_array($d->id, old('district_ids', $draft['district_ids'] ?? []) ?? []))>{{ $d->name_th }}</option>@endforeach
                    </select>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <select name="audience" class="form-select">
                                <option value="public" @selected(old('audience') !== 'staff')>ประชาชน</option>
                                <option value="staff" @selected(old('audience') === 'staff')>เฉพาะเจ้าหน้าที่</option>
                            </select>
                        </div>
                        <div class="col-6"><input name="hours" type="number" min="1" max="720" class="form-control" placeholder="มีผลกี่ชั่วโมง" value="{{ old('hours') }}"></div>
                    </div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="pinned" value="1" id="annPin" @checked(old('pinned'))><label class="form-check-label" for="annPin">ปักหมุดบนหน้าแรก</label></div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="send_line" value="1" id="annLine" @checked(old('send_line', $lineReady)) @disabled(! $lineReady)>
                        <label class="form-check-label" for="annLine">ส่งทาง LINE OA {{ $lineReady ? '' : '(ยังไม่ได้ตั้งค่า)' }}</label>
                    </div>
                    <div class="form-text">ประชาชน = ส่งถึงผู้ติดตาม LINE OA ทุกคน · เจ้าหน้าที่ = ส่งถึงผู้ใช้ที่ผูก LINE ไว้</div>
                </div>
                <div class="card-footer bg-transparent d-flex gap-2 justify-content-end">
                    <button class="btn btn-light" name="action" value="draft" type="submit">บันทึกร่าง</button>
                    <button class="btn btn-primary" name="action" value="publish" type="submit"><i class="bi bi-send me-1"></i>เผยแพร่</button>
                </div>
            </form>
        </div>
        <div class="col-xl-7">
            <div class="card">
                @forelse($items as $a)
                    @php $live = $a->published_at && (! $a->expires_at || $a->expires_at->isFuture()); @endphp
                    <div class="list-row {{ $live || ! $a->published_at ? '' : 'opacity-50' }}">
                        <i class="bi bi-{{ $a->icon() }} fs-5 mt-1" style="color:{{ $a->color() }}"></i>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex flex-wrap align-items-center gap-1">
                                <span class="fw-600 me-1">{{ $a->title }}</span>
                                <span class="chip {{ $a->levelChip() }}">{{ $a->levelLabel() }}</span>
                                @if($a->pinned)<span class="chip"><i class="bi bi-pin-angle"></i></span>@endif
                                @if($a->audience === 'staff')<span class="chip">เจ้าหน้าที่</span>@endif
                                @if(! $a->published_at)<span class="chip chip-warning">ร่าง</span>@elseif(! $live)<span class="chip">สิ้นสุด</span>@endif
                                @if($a->line_status === 'sent')<span class="chip chip-success"><i class="bi bi-chat-dots"></i> LINE{{ $a->line_recipients ? ' '.$a->line_recipients.' คน' : '' }}</span>
                                @elseif($a->line_status === 'failed')<span class="chip chip-danger" title="{{ $a->line_error }}"><i class="bi bi-chat-dots"></i> ส่งไม่สำเร็จ</span>
                                @elseif($a->line_status === 'pending')<span class="chip chip-warning"><i class="bi bi-hourglass-split"></i> กำลังส่ง LINE</span>
                                @elseif($a->line_status === 'skipped')<span class="chip" title="{{ $a->line_error }}">ไม่ได้ส่ง LINE</span>@endif
                            </div>
                            <div class="small mt-1" style="white-space:pre-line">{{ \Illuminate\Support\Str::limit($a->body, 240) }}</div>
                            <div class="small text-muted mt-1">{{ $a->districtNames() }} · {{ $a->creator?->name }} · {{ thai_date($a->published_at ?? $a->created_at, 'compact') }}{{ $a->expires_at ? ' ถึง '.thai_date($a->expires_at, 'compact') : '' }}</div>
                        </div>
                        <div class="d-flex flex-wrap gap-1 flex-shrink-0 justify-content-end" style="max-width:170px">
                            @if(! $a->published_at)
                                <form method="POST" action="{{ route('announcements.publish', $a) }}">@csrf<button class="btn btn-sm btn-primary" type="submit">เผยแพร่</button></form>
                            @elseif($live)
                                @if($lineReady && $a->line_status !== 'sent')
                                    <form method="POST" action="{{ route('announcements.resend', $a) }}">@csrf<button class="btn btn-sm btn-light" type="submit"><i class="bi bi-chat-dots"></i> ส่ง LINE</button></form>
                                @endif
                                <form method="POST" action="{{ route('announcements.unpublish', $a) }}" data-confirm="ยกเลิกประกาศนี้?">@csrf<button class="btn btn-sm btn-light" type="submit">ยกเลิก</button></form>
                            @endif
                            <form method="POST" action="{{ route('announcements.destroy', $a) }}" data-confirm="ลบประกาศนี้?">@csrf @method('DELETE')<button class="btn btn-sm btn-light btn-icon text-danger" type="submit"><i class="bi bi-trash"></i></button></form>
                        </div>
                    </div>
                @empty
                    <x-empty icon="megaphone" title="ยังไม่มีประกาศ" text="ประกาศจุดอพยพ เส้นทางที่ปิด หรือข่าวสำคัญ ส่งพร้อมกันทั้งเว็บและ LINE" />
                @endforelse
            </div>
            <div class="mt-3">{{ $items->links() }}</div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const b = document.getElementById('annBody'), c = document.getElementById('annCount');
            const u = () => (c.textContent = b.value.length);
            b.addEventListener('input', u); u();
        })();
    </script>
@endpush
