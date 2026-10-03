@props(['id', 'title', 'action', 'method' => 'POST', 'size' => null, 'submit' => 'บันทึก', 'files' => false])
{{--
  modal ฟอร์มเพิ่ม/แก้ไขที่ใช้ซ้ำ
  - เปิดด้วยปุ่ม data-form-modal="#id" (ดู public/js/app.js)
  - ถ้า validate ไม่ผ่าน จะเปิด modal เดิมพร้อม action/method เดิมให้อัตโนมัติ
--}}
@php
    $reopen = old('_modal') === '#'.$id;
    $formAction = $reopen ? old('_action', $action) : $action;
    $formMethod = $reopen ? old('_method', $method) : $method;
@endphp
<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable {{ $size ? 'modal-'.$size : '' }}">
        <form class="modal-content" method="POST" action="{{ $formAction }}" data-default-action="{{ $action }}" @if($files) enctype="multipart/form-data" @endif novalidate>
            @csrf
            <input type="hidden" name="_method" value="{{ $formMethod }}">
            <input type="hidden" name="_modal" value="#{{ $id }}">
            <input type="hidden" name="_action" value="{{ $formAction }}">
            <div class="modal-header">
                <h5 class="modal-title">{{ $title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <div class="modal-body">
                @if($reopen && $errors->any())
                    <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle"></i><div>{{ $errors->first() }}</div></div>
                @endif
                {{ $slot }}
            </div>
            <div class="modal-footer">
                @isset($footer){{ $footer }}@endisset
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">{{ $submit }}</button>
            </div>
        </form>
    </div>
</div>
