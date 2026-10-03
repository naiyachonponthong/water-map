@extends('errors.layout')
@section('code', '403')
@section('title', 'ไม่มีสิทธิ์เข้าถึง')
@section('message'){{ preg_match('/[\x{0E00}-\x{0E7F}]/u', $exception->getMessage()) ? $exception->getMessage() : 'บัญชีของคุณไม่มีสิทธิ์ใช้หน้านี้ ติดต่อผู้อำนวยการศูนย์ของจังหวัด' }}@endsection
