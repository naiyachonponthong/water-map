<?php

use App\Models\TeamMember;
use Illuminate\Support\Facades\Broadcast;

// ห้องสั่งการของจังหวัด: เคสและทีมเปลี่ยน
Broadcast::channel('province.{id}', function ($user, int $id) {
    return $user->isActive()
        && ($user->can('dashboard.view') || $user->can('dispatch.manage') || $user->can('cases.view'))
        && $user->canManageProvince($id);
});

// ทีม: งานใหม่ที่ศูนย์เสนอ / งานถูกยกเลิก
Broadcast::channel('team.{id}', function ($user, int $id) {
    return $user->isActive() && TeamMember::where('team_id', $id)->where('user_id', $user->id)->exists();
});
