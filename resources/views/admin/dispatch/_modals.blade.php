@use('App\Support\CaseOptions')
@use('App\Support\TeamOptions')
    {{-- มอบหมายทีม --}}
    <div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">มอบหมายทีม</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body" id="assignBody"></div>
            </div>
        </div>
    </div>

    {{-- ปิดงาน --}}
    <div class="modal fade" id="completeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" id="completeForm">
                @csrf
                <div class="modal-header"><h5 class="modal-title">ปิดงาน <span class="mono" id="completeCode"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label req">ผลการช่วยเหลือ</label>
                    <select name="outcome" class="form-select mb-3">
                        @foreach(CaseOptions::OUTCOMES as $k => $l)
                            @continue(in_array($k, ['duplicate', 'self_safe'], true))
                            <option value="{{ $k }}">{{ $l }}</option>
                        @endforeach
                    </select>
                    <label class="form-label">ช่วยออกมาได้กี่คน</label>
                    <input type="number" name="people_rescued" class="form-control mb-3" min="0" max="500">
                    <label class="form-label">หมายเหตุ</label>
                    <input name="note" class="form-control" maxlength="500">
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-success" type="submit">ปิดงาน</button></div>
            </form>
        </div>
    </div>

    {{-- ปฏิเสธ / ยกเลิก (ต้องมีเหตุผล) --}}
    <div class="modal fade" id="reasonModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" id="reasonForm">
                @csrf
                <input type="hidden" name="accept" value="0">
                <div class="modal-header"><h5 class="modal-title" id="reasonTitle">เหตุผล</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <select name="reason" class="form-select mb-2" id="reasonSelect">
                        @foreach(TeamOptions::DECLINE_REASONS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                    </select>
                    <input name="note" class="form-control" maxlength="300" placeholder="รายละเอียด (ไม่บังคับ)" id="reasonNote">
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">ปิด</button><button class="btn btn-danger" type="submit">ยืนยัน</button></div>
            </form>
        </div>
    </div>

    <form method="POST" id="actForm" class="d-none">@csrf<input type="hidden" name="accept"><input type="hidden" name="step"></form>
