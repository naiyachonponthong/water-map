{{-- คอลัมน์ของกระดานสั่งการ (โหลดซ้ำทุก 20 วินาที) --}}
<div class="kanban" id="kanban" data-triage="{{ $triage }}">
    @foreach($columns as $key => $col)
        <div class="kb-col">
            <div class="kb-head">
                <span class="kb-dot tint-{{ $col['tone'] }}"></span>{{ $col['label'] }}
                <span class="kb-count">{{ $col['cases']->count() }}</span>
            </div>
            <div class="kb-list" data-col="{{ $key }}">
                @foreach($col['cases'] as $c)
                    @php $a = $c->assignment; @endphp
                    <div class="kb-card" data-case="{{ $c->id }}" data-assignment="{{ $a?->id }}" data-code="{{ $c->code }}" data-status="{{ $c->status }}" style="border-left-color:{{ $c->priorityColor() }}">
                        <div class="d-flex align-items-center gap-1 mb-1">
                            <a href="{{ route('cases.show', $c) }}" class="mono small text-muted">{{ $c->code }}</a>
                            <span class="score-pill ms-auto" style="background:{{ $c->priorityColor() }}">{{ $c->priority_score }}</span>
                        </div>
                        <div class="fw-600 small text-truncate">{{ $c->requester_name }}</div>
                        <div class="cr-meta">
                            <span>น้ำ{{ $c->waterLabel() }}</span><span>{{ $c->people_count }} คน</span>
                            @if($c->hasVulnerable('bedridden', 'sick', 'disabled', 'infant'))<span class="text-danger"><i class="bi bi-heart-pulse"></i></span>@endif
                        </div>
                        <div class="small text-muted text-truncate"><i class="bi bi-geo-alt"></i> {{ $c->areaLabel() }}</div>
                        @if($c->team)
                            <div class="small mt-1 text-truncate"><i class="bi bi-people-fill text-primary"></i> {{ $c->team->name }}
                                @if($a?->eta_minutes && in_array($a->status, ['offered', 'accepted', 'en_route'], true))<span class="text-muted">· {{ $a->eta_minutes }} นาที</span>@endif
                            </div>
                        @endif
                        <div class="kb-foot">
                            <span class="small text-muted">{{ $key === 'rescued' ? thai_date($c->closed_at, 'ago') : 'รอ '.$c->waitingLabel() }}</span>
                            @if($key !== 'rescued')
                                <div class="dropdown ms-auto">
                                    <button class="btn btn-sm btn-light btn-icon" data-bs-toggle="dropdown" type="button" aria-label="จัดการ"><i class="bi bi-three-dots"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        @if($key === 'queued')
                                            <li><button class="dropdown-item" data-assign="{{ $c->id }}"><i class="bi bi-send me-2"></i>มอบหมายทีม</button></li>
                                        @endif
                                        @if($a && $a->status === 'offered')
                                            <li><button class="dropdown-item" data-act="respond" data-a="{{ $a->id }}" data-accept="1"><i class="bi bi-check-lg me-2"></i>ทีมรับแล้ว (ยืนยันทางโทรศัพท์)</button></li>
                                            <li><button class="dropdown-item" data-act="respond" data-a="{{ $a->id }}" data-accept="0"><i class="bi bi-x-lg me-2"></i>ทีมปฏิเสธ</button></li>
                                        @endif
                                        @if($a && $a->status === 'accepted')
                                            <li><button class="dropdown-item" data-act="progress" data-a="{{ $a->id }}" data-step="en_route"><i class="bi bi-truck me-2"></i>เริ่มเดินทาง</button></li>
                                        @endif
                                        @if($a && in_array($a->status, ['accepted', 'en_route'], true))
                                            <li><button class="dropdown-item" data-act="progress" data-a="{{ $a->id }}" data-step="on_site"><i class="bi bi-geo-alt me-2"></i>ถึงที่เกิดเหตุ</button></li>
                                        @endif
                                        @if($a && in_array($a->status, ['accepted', 'en_route', 'on_site'], true))
                                            <li><button class="dropdown-item" data-complete="{{ $a->id }}" data-people="{{ $c->people_count }}" data-code="{{ $c->code }}"><i class="bi bi-check2-circle me-2"></i>ปิดงาน</button></li>
                                        @endif
                                        @if($a && $a->isActive())
                                            <li><hr class="dropdown-divider"></li>
                                            <li><button class="dropdown-item text-danger" data-act="cancel" data-a="{{ $a->id }}"><i class="bi bi-arrow-counterclockwise me-2"></i>ยกเลิกงาน คืนเข้าคิว</button></li>
                                        @endif
                                        <li><a class="dropdown-item" href="{{ route('cases.show', $c) }}"><i class="bi bi-box-arrow-up-right me-2"></i>เปิดเคส</a></li>
                                    </ul>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
