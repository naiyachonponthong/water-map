<?php

return [

    // New installations may serve validated images through PHP when symlinks are unavailable.
    'public_uploads_through_app' => (bool) env('PUBLIC_UPLOADS_THROUGH_APP', false),
    'hosting_mode' => env('FLOOD_HOSTING_MODE', 'vps'),

    /*
    | บทบาทในระบบ (key => ชื่อไทย) ลำดับนี้ใช้แสดงผลในหน้าจัดการผู้ใช้
    */
    'roles' => [
        'super-admin' => 'ผู้ดูแลระบบสูงสุด',
        'province-admin' => 'ผู้อำนวยการศูนย์',
        'dispatcher' => 'เจ้าหน้าที่สั่งการ',
        'moderator' => 'ผู้ตรวจรายงาน',
        'team-leader' => 'หัวหน้าทีมกู้ภัย',
        'team-member' => 'สมาชิกทีมกู้ภัย',
        'shelter-staff' => 'เจ้าหน้าที่ศูนย์พักพิง',
    ],

    /*
    | บทบาทที่ต้องผูกกับจังหวัด (ทุกบทบาทยกเว้น super-admin)
    */
    'province_scoped_roles' => [
        'province-admin', 'dispatcher', 'moderator', 'team-leader', 'team-member', 'shelter-staff',
    ],

    /*
    | ระดับน้ำ 6 ขั้น ใช้ร่วมกันทั้งหน้าประชาชนและหลังบ้าน
    */
    'water_levels' => [
        1 => ['label' => 'แห้ง / ต่ำกว่าข้อเท้า', 'short' => 'น้ำแห้ง', 'range' => '< 10 ซม.', 'color' => '#22c55e'],
        2 => ['label' => 'ข้อเท้า ถึง เข่า', 'short' => 'ระดับเข่า', 'range' => '10-50 ซม.', 'color' => '#facc15'],
        3 => ['label' => 'เข่า ถึง เอว', 'short' => 'ระดับเอว', 'range' => '50-100 ซม.', 'color' => '#fb923c'],
        4 => ['label' => 'เอว ถึง อก', 'short' => 'ระดับอก', 'range' => '100-130 ซม.', 'color' => '#ef4444'],
        5 => ['label' => 'อกขึ้นไป', 'short' => 'เกินอก', 'range' => '130-180 ซม.', 'color' => '#a21caf'],
        6 => ['label' => 'มิดหัว / ท่วมหลังคา', 'short' => 'มิดหัว', 'range' => '> 180 ซม.', 'color' => '#4c1d95'],
    ],

    /*
    | ค่าตั้งต้นระดับจังหวัด (ปรับได้ในหน้า ตั้งค่า ต่อจังหวัด)
    */
    'defaults' => [
        'theme_color' => '#1565C0',
        'hotline' => '1784',
        'team_self_assign' => true,
        'report_fade_hours' => [12, 24],
        'report_expire_hours' => 36,
        'reports_open' => true,
        'report_premoderate' => false,
        'recovery_open' => false,
        'recovery_rates' => [],
        'rain_warning_mm' => 35,
        'rain_critical_mm' => 90,
        'duplicate_radius_m' => 100,
        'duplicate_window_hours' => 6,
        'risk_alert_radius_m' => 1000,
        'offer_timeout_min' => 10,
        'household_trigger_level' => 2,
        'proactive_cooldown_hours' => 12,
        'priority_weights' => [
            'water_level' => 10,
            'bedridden' => 30,
            'child_elderly' => 15,
            'no_food' => 10,
            'per_hour_waiting' => 5,
        ],
    ],

    /*
    | เบอร์ฉุกเฉินระดับประเทศ ใช้เมื่อจังหวัดยังไม่ได้ตั้งเบอร์เอง
    */
    'national_contacts' => [
        ['label' => 'สายด่วนนิรภัย ปภ. (ภัยพิบัติ น้ำท่วม)', 'phone' => '1784'],
        ['label' => 'การแพทย์ฉุกเฉิน (เจ็บป่วย อุบัติเหตุ)', 'phone' => '1669'],
        ['label' => 'เหตุด่วนเหตุร้าย', 'phone' => '191'],
    ],

    // IP ของ reverse proxy (nginx, load balancer, Cloudflare) คั่นด้วยจุลภาค หรือ * ถ้าเชื่อทุกตัว
    'trusted_proxies' => env('TRUSTED_PROXIES', '127.0.0.1'),

    // จำกัดการส่งฟอร์มต่อ IP (คนทั้งชุมชนอาจใช้ IP เดียวกันผ่านมือถือ จึงหลวมกว่าต่อเครื่อง)
    'help_ip_limit' => (int) env('HELP_IP_LIMIT', 40),      // ต่อ 30 นาที
    'report_ip_limit' => (int) env('REPORT_IP_LIMIT', 60),  // ต่อ 10 นาที

    // บทบาทที่ต้องเปิดยืนยันตัวตน 2 ชั้น (คั่นด้วยจุลภาค เว้นว่าง = ไม่บังคับ)
    'require_2fa_roles' => env('REQUIRE_2FA_ROLES', 'super-admin,province-admin,dispatcher'),

    // ไม่ใช้งานกี่นาทีแล้วออกจากระบบอัตโนมัติ (หลังบ้าน) แอปภาคสนามและจอทีวีไม่นับ
    'idle_timeout_min' => (int) env('IDLE_TIMEOUT_MIN', 120),

    // เก็บข้อมูลส่วนบุคคลหลังปิดเคส/ออกจากศูนย์กี่วัน แล้วลบเบอร์โทรทิ้ง (PDPA)
    'pii_retention_days' => (int) env('PII_RETENTION_DAYS', 180),

    'line' => [
        'channel_id' => env('LINE_LOGIN_CHANNEL_ID'),
        'channel_secret' => env('LINE_LOGIN_CHANNEL_SECRET'),
        'callback' => env('LINE_LOGIN_CALLBACK'),
    ],

    'superadmin' => [
        'phone' => env('SUPERADMIN_PHONE', '0800000000'),
        'password' => env('SUPERADMIN_PASSWORD', 'floodthai@2026'),
    ],
];
