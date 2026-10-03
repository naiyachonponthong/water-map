@extends('layouts.app')
@section('title', 'งานของทีมฉัน')

@section('content')
    <x-page-head title="งานของทีมฉัน" />
    <div class="card">
        <x-empty icon="people" title="คุณยังไม่ได้อยู่ในทีมใด" text="ติดต่อเจ้าหน้าที่ศูนย์ให้เพิ่มคุณเข้าทีม แล้วงานที่ศูนย์มอบหมายจะแสดงที่หน้านี้" />
    </div>
@endsection
