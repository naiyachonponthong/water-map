<?php

use App\Http\Controllers\Admin\AreaController;
use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\DispatchController;
use App\Http\Controllers\Admin\MyTeamController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\RiskController;
use App\Http\Controllers\Admin\HouseholdController;
use App\Http\Controllers\Public\RiskProposalController;
use App\Http\Controllers\Public\WaterReportController;
use App\Http\Controllers\Admin\StationController;
use App\Http\Controllers\Admin\CameraController;
use App\Http\Controllers\Admin\AlertController;
use App\Http\Controllers\Admin\ShelterController;
use App\Http\Controllers\Admin\SupplyController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Public\OpenDataController;
use App\Http\Controllers\Public\RecoveryController;
use App\Http\Controllers\Admin\RecoveryController as AdminRecoveryController;
use App\Http\Controllers\Public\ReliefController;
use App\Http\Controllers\Line\WebhookController as LineWebhookController;
use App\Http\Controllers\Admin\WaterReportController as AdminReportController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CaseController;
use App\Http\Controllers\Admin\EmergencyContactController;
use App\Http\Controllers\Admin\ExternalLinkController;
use App\Http\Controllers\Admin\ProvinceController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LineLoginController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\Admin\SosController;
use App\Http\Controllers\LiveController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\HelpController;
use App\Http\Controllers\Public\PublicController;
use App\Http\Controllers\Public\TrackController;
use Illuminate\Support\Facades\Route;

Route::get('/media/{path}', [\App\Http\Controllers\Public\PublicImageController::class, 'show'])
    ->where('path', '.*')->name('public.image');

/*
| เข้าสู่ระบบ / สมัครเจ้าหน้าที่
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
});

// ยืนยันตัวตน 2 ชั้นหลังรหัสผ่านถูกต้อง
Route::middleware('guest')->group(function () {
    Route::get('/login/two-factor', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/login/two-factor', [TwoFactorController::class, 'verify'])->middleware('throttle:5,1')->name('two-factor.verify');
});

Route::get('/auth/line', [LineLoginController::class, 'redirect'])->name('line.redirect');
Route::get('/auth/line/callback', [LineLoginController::class, 'callback'])->name('line.callback');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/pending', [RegisterController::class, 'pending'])->middleware('active')->name('pending');
});

/*
| หลังบ้าน
*/
Route::middleware(['auth', 'active', 'idle', '2fa'])->prefix('admin')->group(function () {
    Route::middleware('role:super-admin')->group(function () {
        Route::get('/setup', [\App\Http\Controllers\Admin\SetupController::class, 'index'])->name('admin.setup');
        Route::post('/setup/province', [\App\Http\Controllers\Admin\SetupController::class, 'selectProvince'])->name('admin.setup.province');
        Route::post('/setup/basics', [\App\Http\Controllers\Admin\SetupController::class, 'saveBasics'])->name('admin.setup.basics');
        Route::post('/setup/review', [\App\Http\Controllers\Admin\SetupController::class, 'saveReview'])->name('admin.setup.review');
    });
    Route::get('/hosting', [\App\Http\Controllers\Admin\HostingController::class, 'index'])
        ->middleware('role:super-admin')->name('admin.hosting');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/areas.geojson', [DashboardController::class, 'areas'])->name('dashboard.areas');
    Route::get('/menu', [MenuController::class, 'index'])->name('menu.index');
    Route::get('/guide', [\App\Http\Controllers\UsageGuideController::class, 'staff'])->name('admin.guide');
    Route::middleware('permission:dashboard.view')->group(function () {
        Route::get('/live/snapshot', [LiveController::class, 'snapshot'])->name('live.snapshot');
        Route::get('/tv', [LiveController::class, 'tv'])->name('live.tv');
    });
    Route::post('/switch-province', [DashboardController::class, 'switchProvince'])->name('province.switch');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::delete('/profile/line', [ProfileController::class, 'unlinkLine'])->name('profile.line.unlink');
    Route::post('/profile/two-factor', [TwoFactorController::class, 'enable'])->name('profile.2fa.enable');
    Route::post('/profile/two-factor/confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:10,1')->name('profile.2fa.confirm');
    Route::post('/profile/two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('profile.2fa.recovery');
    Route::delete('/profile/two-factor', [TwoFactorController::class, 'disable'])->name('profile.2fa.disable');

    Route::middleware('permission:cases.view')->group(function () {
        Route::get('/cases', [CaseController::class, 'index'])->name('cases.index');
        Route::get('/cases/map.geojson', [CaseController::class, 'geojson'])->name('cases.geojson');
        Route::get('/cases/poll', [CaseController::class, 'poll'])->name('cases.poll');
        Route::get('/cases/{case}', [CaseController::class, 'show'])->whereNumber('case')->name('cases.show');
    });
    Route::middleware('permission:cases.manage')->group(function () {
        Route::get('/cases/create', [CaseController::class, 'create'])->name('cases.create');
        Route::post('/cases', [CaseController::class, 'store'])->name('cases.store');
        Route::get('/cases/{case}/edit', [CaseController::class, 'edit'])->name('cases.edit');
        Route::put('/cases/{case}', [CaseController::class, 'update'])->name('cases.update');
        Route::post('/cases/{case}/screen', [CaseController::class, 'screen'])->name('cases.screen');
        Route::post('/cases/{case}/status', [CaseController::class, 'status'])->name('cases.status');
        Route::post('/cases/{case}/priority', [CaseController::class, 'priority'])->name('cases.priority');
        Route::post('/cases/{case}/merge', [CaseController::class, 'merge'])->name('cases.merge');
        Route::post('/cases/{case}/not-duplicate', [CaseController::class, 'notDuplicate'])->name('cases.not-duplicate');
        Route::post('/cases/{case}/note', [CaseController::class, 'note'])->name('cases.note');
    });

    // ทีมกู้ภัย
    Route::middleware('permission:teams.manage')->group(function () {
        Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
        Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
        Route::get('/teams/{team}', [TeamController::class, 'show'])->name('teams.show');
        Route::put('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
        Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
        Route::post('/teams/{team}/members', [TeamController::class, 'addMember'])->name('teams.members.store');
        Route::delete('/teams/{team}/members/{user}', [TeamController::class, 'removeMember'])->name('teams.members.destroy');
        Route::post('/teams/{team}/members/{user}/leader', [TeamController::class, 'makeLeader'])->name('teams.members.leader');
        Route::post('/teams/{team}/vehicles', [TeamController::class, 'storeVehicle'])->name('teams.vehicles.store');
        Route::put('/vehicles/{vehicle}', [TeamController::class, 'updateVehicle'])->name('vehicles.update');
        Route::delete('/vehicles/{vehicle}', [TeamController::class, 'destroyVehicle'])->name('vehicles.destroy');
    });
    Route::post('/teams/{team}/status', [TeamController::class, 'status'])->name('teams.status');

    // สั่งการ
    Route::middleware('permission:dispatch.manage')->group(function () {
        Route::get('/dispatch', [DispatchController::class, 'index'])->name('dispatch.index');
        Route::get('/cases/{case}/suggest', [DispatchController::class, 'suggest'])->name('dispatch.suggest');
        Route::post('/cases/{case}/assign', [DispatchController::class, 'assign'])->name('dispatch.assign');
        Route::post('/assignments/{assignment}/cancel', [AssignmentController::class, 'cancel'])->name('assignments.cancel');
    });
    Route::post('/assignments/{assignment}/respond', [AssignmentController::class, 'respond'])->name('assignments.respond');
    Route::post('/assignments/{assignment}/progress', [AssignmentController::class, 'progress'])->name('assignments.progress');
    Route::post('/assignments/{assignment}/complete', [AssignmentController::class, 'complete'])->name('assignments.complete');

    // SOS ของทีม
    Route::middleware('permission:dispatch.manage')->group(function () {
        Route::post('/sos/{sos}/ack', [SosController::class, 'ack'])->name('sos.ack');
        Route::post('/sos/{sos}/resolve', [SosController::class, 'resolve'])->name('sos.resolve');
    });

    // งานของทีม
    Route::middleware('permission:field.use')->group(function () {
        Route::get('/my-team', [MyTeamController::class, 'index'])->name('my-team.index');
        Route::post('/my-team/pick/{case}', [MyTeamController::class, 'pick'])->name('my-team.pick');
        Route::post('/my-team/location', [MyTeamController::class, 'location'])->middleware('throttle:10,1')->name('my-team.location');
    });

    // จุดเสี่ยง + ประกาศระดับน้ำรายตำบล
    Route::middleware('permission:risks.manage')->group(function () {
        Route::get('/risks', [RiskController::class, 'index'])->name('risks.index');
        Route::get('/risks/map.geojson', [RiskController::class, 'geojson'])->name('risks.geojson');
        Route::post('/risks', [RiskController::class, 'store'])->name('risks.store');
        Route::post('/risks/import', [RiskController::class, 'import'])->name('risks.import');
        Route::post('/risks/evaluate', [RiskController::class, 'evaluate'])->name('risks.evaluate');
        Route::post('/risks/levels', [RiskController::class, 'declareLevel'])->name('risks.levels.store');
        Route::delete('/risks/levels/{level}', [RiskController::class, 'endLevel'])->name('risks.levels.end');
        Route::put('/risks/{risk}', [RiskController::class, 'update'])->name('risks.update');
        Route::delete('/risks/{risk}', [RiskController::class, 'destroy'])->name('risks.destroy');
        Route::post('/risks/{risk}/review', [RiskController::class, 'review'])->name('risks.review');
    });

    // รายงานระดับน้ำ (คัดกรอง)
    Route::middleware('permission:reports.moderate')->group(function () {
        Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/map.geojson', [AdminReportController::class, 'geojson'])->name('reports.geojson');
        Route::post('/reports', [AdminReportController::class, 'store'])->name('reports.store');
        Route::post('/reports/bulk', [AdminReportController::class, 'bulk'])->name('reports.bulk');
        Route::post('/reports/{report}/moderate', [AdminReportController::class, 'moderate'])->name('reports.moderate');
    });

    // สถานีวัดน้ำ + พยากรณ์ฝน
    Route::middleware('permission:stations.manage')->group(function () {
        Route::get('/stations', [StationController::class, 'index'])->name('stations.index');
        Route::get('/stations/map.geojson', [StationController::class, 'geojson'])->name('stations.geojson');
        Route::post('/stations', [StationController::class, 'store'])->name('stations.store');
        Route::post('/stations/forecast', [StationController::class, 'forecast'])->name('stations.forecast');
        Route::get('/stations/{station}', [StationController::class, 'show'])->whereNumber('station')->name('stations.show');
        Route::put('/stations/{station}', [StationController::class, 'update'])->name('stations.update');
        Route::delete('/stations/{station}', [StationController::class, 'destroy'])->name('stations.destroy');
        Route::post('/stations/{station}/readings', [StationController::class, 'reading'])->name('stations.reading');
        Route::post('/stations/{station}/import', [StationController::class, 'import'])->name('stations.import');
        Route::post('/stations/{station}/fetch', [StationController::class, 'fetch'])->name('stations.fetch');
    });

    // กล้อง CCTV
    Route::middleware('permission:cctv.manage')->group(function () {
        Route::get('/cctv', [CameraController::class, 'index'])->name('cctv.index');
        Route::post('/cctv', [CameraController::class, 'store'])->name('cctv.store');
        Route::put('/cctv/{camera}', [CameraController::class, 'update'])->name('cctv.update');
        Route::delete('/cctv/{camera}', [CameraController::class, 'destroy'])->name('cctv.destroy');
    });

    // เตือนภัยล่วงหน้า
    Route::middleware('permission:dashboard.view')->group(function () {
        Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
        Route::post('/alerts/{alert}/ack', [AlertController::class, 'acknowledge'])->name('alerts.ack');
    });
    Route::middleware('permission:announcements.manage')->group(function () {
        Route::post('/alerts', [AlertController::class, 'store'])->name('alerts.store');
        Route::post('/alerts/{alert}/resolve', [AlertController::class, 'resolve'])->name('alerts.resolve');
    });

    // ศูนย์พักพิง + ผู้อพยพ
    Route::middleware('permission:shelters.manage|evacuees.manage')->group(function () {
        Route::get('/shelters', [ShelterController::class, 'index'])->name('shelters.index');
        Route::get('/shelters/{shelter}', [ShelterController::class, 'show'])->whereNumber('shelter')->name('shelters.show');
        Route::post('/shelters/{shelter}/status', [ShelterController::class, 'status'])->name('shelters.status');
        Route::post('/shelters/{shelter}/needs', [ShelterController::class, 'need'])->name('shelters.needs.store');
        Route::post('/shelter-needs/{need}/toggle', [ShelterController::class, 'needDone'])->name('shelters.needs.toggle');
    });
    Route::middleware('permission:shelters.manage')->group(function () {
        Route::post('/shelters', [ShelterController::class, 'store'])->name('shelters.store');
        Route::put('/shelters/{shelter}', [ShelterController::class, 'update'])->name('shelters.update');
        Route::delete('/shelters/{shelter}', [ShelterController::class, 'destroy'])->name('shelters.destroy');
        Route::put('/shelters/{shelter}/staff', [ShelterController::class, 'staff'])->name('shelters.staff');
    });
    Route::middleware('permission:evacuees.manage')->group(function () {
        Route::get('/shelters/{shelter}/register', [ShelterController::class, 'registerForm'])->name('shelters.register');
        Route::post('/shelters/{shelter}/register', [ShelterController::class, 'register'])->name('shelters.register.store');
        Route::post('/evacuees/{evacuee}/checkout', [ShelterController::class, 'checkout'])->name('evacuees.checkout');
        Route::post('/evacuees/{evacuee}/transfer', [ShelterController::class, 'transfer'])->name('evacuees.transfer');
    });

    // คลังของบริจาค
    Route::middleware('permission:supplies.manage')->group(function () {
        Route::get('/supplies', [SupplyController::class, 'index'])->name('supplies.index');
        Route::post('/supplies/items', [SupplyController::class, 'storeItem'])->name('supplies.items.store');
        Route::put('/supplies/items/{item}', [SupplyController::class, 'updateItem'])->name('supplies.items.update');
        Route::post('/supplies/move', [SupplyController::class, 'move'])->name('supplies.move');
    });

    // ประกาศ + LINE OA
    Route::middleware('permission:announcements.manage')->group(function () {
        Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::post('/announcements/{announcement}/publish', [AnnouncementController::class, 'publish'])->name('announcements.publish');
        Route::post('/announcements/{announcement}/resend', [AnnouncementController::class, 'resend'])->name('announcements.resend');
        Route::post('/announcements/{announcement}/unpublish', [AnnouncementController::class, 'unpublish'])->name('announcements.unpublish');
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    });

    // รายงานผู้บริหาร / ส่งออก
    Route::middleware('permission:exports.view')->group(function () {
        Route::get('/exports', [ExportController::class, 'index'])->name('exports.index');
        Route::get('/exports/sitrep', [ExportController::class, 'sitrep'])->name('exports.sitrep');
        Route::get('/exports/download/{dataset}', [ExportController::class, 'download'])->where('dataset', '[a-z]+')->middleware('throttle:20,1')->name('exports.download');
    });

    // โหมดฟื้นฟูหลังน้ำลด
    Route::middleware('permission:recovery.manage|recovery.survey')->group(function () {
        Route::get('/recovery', [AdminRecoveryController::class, 'index'])->name('recovery.index');
        Route::get('/recovery/{claim}', [AdminRecoveryController::class, 'show'])->whereNumber('claim')->name('recovery.show');
        Route::post('/recovery/{claim}/action', [AdminRecoveryController::class, 'action'])->name('recovery.action');
    });
    Route::middleware('permission:recovery.manage')->group(function () {
        Route::get('/recovery/create', [AdminRecoveryController::class, 'create'])->name('recovery.create');
        Route::post('/recovery', [AdminRecoveryController::class, 'store'])->name('recovery.store');
        Route::put('/recovery/settings', [AdminRecoveryController::class, 'rates'])->name('recovery.rates');
    });

    // ครัวเรือนกลุ่มเปราะบาง
    Route::middleware('permission:vulnerable.manage')->prefix('vulnerable')->name('vulnerable.')->group(function () {
        Route::get('/', [HouseholdController::class, 'index'])->name('index');
        Route::get('/create', [HouseholdController::class, 'create'])->name('create');
        Route::get('/template.csv', [HouseholdController::class, 'template'])->name('template');
        Route::post('/', [HouseholdController::class, 'store'])->name('store');
        Route::post('/import', [HouseholdController::class, 'import'])->name('import');
        Route::get('/{household}', [HouseholdController::class, 'show'])->whereNumber('household')->name('show');
        Route::get('/{household}/edit', [HouseholdController::class, 'edit'])->name('edit');
        Route::put('/{household}', [HouseholdController::class, 'update'])->name('update');
        Route::delete('/{household}', [HouseholdController::class, 'destroy'])->name('destroy');
        Route::post('/{household}/check', [HouseholdController::class, 'check'])->name('check');
        Route::post('/{household}/open-case', [HouseholdController::class, 'openCase'])->name('open-case');
    });

    Route::name('admin.')->group(function () {
        Route::middleware('permission:users.manage')->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
            Route::post('/users/{user}/approve', [UserController::class, 'approve'])->name('users.approve');
            Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
            Route::post('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
            Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::post('/users/{user}/reset-2fa', [UserController::class, 'resetTwoFactor'])->name('users.reset-2fa');
        });

        Route::middleware('permission:areas.manage')->group(function () {
            Route::get('/areas', [AreaController::class, 'index'])->name('areas.index');
            Route::get('/areas/map.geojson', [AreaController::class, 'geojson'])->name('areas.geojson');
            Route::post('/areas/districts', [AreaController::class, 'storeDistrict'])->name('areas.districts.store');
            Route::put('/areas/districts/{district}', [AreaController::class, 'updateDistrict'])->name('areas.districts.update');
            Route::delete('/areas/districts/{district}', [AreaController::class, 'destroyDistrict'])->name('areas.districts.destroy');
            Route::post('/areas/subdistricts', [AreaController::class, 'storeSubdistrict'])->name('areas.subdistricts.store');
            Route::put('/areas/subdistricts/{subdistrict}', [AreaController::class, 'updateSubdistrict'])->name('areas.subdistricts.update');
            Route::delete('/areas/subdistricts/{subdistrict}', [AreaController::class, 'destroySubdistrict'])->name('areas.subdistricts.destroy');
            Route::post('/areas/import', [AreaController::class, 'import'])->name('areas.import');
        });

        Route::middleware('permission:provinces.manage')->group(function () {
            Route::get('/provinces', [ProvinceController::class, 'index'])->name('provinces.index');
            Route::put('/provinces/{prov}', [ProvinceController::class, 'update'])->name('provinces.update');
        });

        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
            Route::put('/settings/general', [SettingController::class, 'general'])->name('settings.general');
            Route::put('/settings/switches', [SettingController::class, 'switches'])->name('settings.switches');
            Route::put('/settings/operation', [SettingController::class, 'operation'])->name('settings.operation');
            Route::put('/settings/line', [SettingController::class, 'line'])->name('settings.line');

            Route::post('/settings/contacts', [EmergencyContactController::class, 'store'])->name('contacts.store');
            Route::put('/settings/contacts/{contact}', [EmergencyContactController::class, 'update'])->name('contacts.update');
            Route::delete('/settings/contacts/{contact}', [EmergencyContactController::class, 'destroy'])->name('contacts.destroy');
            Route::post('/settings/contacts/sort', [EmergencyContactController::class, 'sort'])->name('contacts.sort');

            Route::post('/settings/links', [ExternalLinkController::class, 'store'])->name('links.store');
            Route::put('/settings/links/{link}', [ExternalLinkController::class, 'update'])->name('links.update');
            Route::delete('/settings/links/{link}', [ExternalLinkController::class, 'destroy'])->name('links.destroy');
        });

        Route::middleware('permission:audit.view')->group(function () {
            Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
            Route::get('/audit/{log}', [AuditLogController::class, 'show'])->name('audit.show');
        });
    });
});

/*
| แอปภาคสนาม (PWA) ของทีมกู้ภัย
*/
Route::middleware(['auth', 'active', '2fa', 'permission:field.use'])->prefix('field')->name('field.')->group(function () {
    Route::get('/', [FieldController::class, 'app'])->name('app');
    Route::get('/api/state', [FieldController::class, 'state'])->name('state');
    Route::post('/api/action', [FieldController::class, 'action'])->middleware('throttle:60,1')->name('action');
    Route::post('/api/location', [FieldController::class, 'location'])->middleware('throttle:10,1')->name('location');
    Route::post('/api/photo', [FieldController::class, 'photo'])->middleware('throttle:20,1')->name('photo');
});

/*
| หน้าเว็บประชาชน (วางท้ายสุด เพราะ /{province} รับทุก slug)
*/
Route::get('/', [PublicController::class, 'root'])->name('home');
Route::get('/provinces', [PublicController::class, 'provinces'])->name('public.provinces');
Route::get('/guide', [\App\Http\Controllers\UsageGuideController::class, 'index'])->name('public.guide');
Route::get('/{province}/guide', [\App\Http\Controllers\UsageGuideController::class, 'showProvince'])->where('province', '[a-z][a-z-]+')->name('public.province.guide');

// ติดตามคำขอ (ลิงก์เซ็นชื่อ)
Route::get('/track', [TrackController::class, 'lookup'])->name('public.track.lookup');
Route::post('/track', [TrackController::class, 'find'])->middleware('throttle:10,1')->name('public.track.find');
Route::middleware('signed')->group(function () {
    Route::get('/track/{code}', [TrackController::class, 'show'])->name('public.track.show');
    Route::post('/track/{code}/safe', [TrackController::class, 'safe'])->name('public.track.safe');
    Route::post('/track/{code}/worse', [TrackController::class, 'worse'])->middleware('throttle:10,10')->name('public.track.worse');
    Route::post('/track/{code}/note', [TrackController::class, 'note'])->middleware('throttle:10,10')->name('public.track.note');
});

// ขอความช่วยเหลือ
Route::get('/{province}/help', [HelpController::class, 'form'])->where('province', '[a-z][a-z-]+')->name('public.help');
Route::post('/{province}/help', [HelpController::class, 'store'])->where('province', '[a-z][a-z-]+')->middleware('throttle:help')->name('public.help.store');
Route::post('/{province}/help/resolve', [HelpController::class, 'resolve'])->where('province', '[a-z][a-z-]+')->middleware('throttle:30,1')->name('public.help.resolve');
// LINE OA webhook (ตรวจลายเซ็นแทน CSRF)
Route::post('/line/webhook/{province}', LineWebhookController::class)->where('province', '[a-z][a-z-]+')->middleware('throttle:120,1')->name('line.webhook');

// ฟื้นฟูหลังน้ำลด: ติดตามคำร้อง (ลิงก์เซ็นชื่อ) + ยื่นคำร้อง
Route::get('/recovery/{code}', [RecoveryController::class, 'track'])->middleware('signed')->name('public.recovery.track');
Route::get('/{province}/recovery', [RecoveryController::class, 'form'])->where('province', '[a-z][a-z-]+')->name('public.recovery');
Route::post('/{province}/recovery', [RecoveryController::class, 'store'])->where('province', '[a-z][a-z-]+')->middleware('throttle:5,30')->name('public.recovery.store');
Route::post('/{province}/recovery/lookup', [RecoveryController::class, 'lookup'])->where('province', '[a-z][a-z-]+')->middleware('throttle:10,10')->name('public.recovery.lookup');

// ประกาศความเป็นส่วนตัว
Route::get('/{province}/privacy', [PublicController::class, 'privacy'])->where('province', '[a-z][a-z-]+')->name('public.privacy');

// ข้อมูลเปิดสำหรับหน่วยงานอื่น (สรุป ไม่มีข้อมูลส่วนบุคคล)
Route::get('/{province}/open-data.json', OpenDataController::class)->where('province', '[a-z][a-z-]+')->middleware('throttle:60,1')->name('public.open-data');

// ศูนย์พักพิง / ค้นหาญาติ / ประกาศ
Route::get('/{province}/shelters', [ReliefController::class, 'shelters'])->where('province', '[a-z][a-z-]+')->name('public.shelters');
Route::get('/{province}/find', [ReliefController::class, 'find'])->where('province', '[a-z][a-z-]+')->name('public.find');
Route::post('/{province}/find', [ReliefController::class, 'lookup'])->where('province', '[a-z][a-z-]+')->middleware('throttle:10,10')->name('public.find.lookup');
Route::get('/{province}/news', [ReliefController::class, 'news'])->where('province', '[a-z][a-z-]+')->name('public.news');

// รายงานระดับน้ำ + แผนที่สถานการณ์
Route::get('/{province}/report', [WaterReportController::class, 'form'])->where('province', '[a-z][a-z-]+')->name('public.reports.create');
Route::post('/{province}/report', [WaterReportController::class, 'store'])->where('province', '[a-z][a-z-]+')->middleware('throttle:report')->name('public.reports.store');
Route::get('/{province}/map', [WaterReportController::class, 'map'])->where('province', '[a-z][a-z-]+')->name('public.map');
Route::get('/{province}/map.geojson', [WaterReportController::class, 'geojson'])->where('province', '[a-z][a-z-]+')->name('public.map.geojson');
Route::post('/{province}/reports/{report}/vote', [WaterReportController::class, 'vote'])->where('province', '[a-z][a-z-]+')->whereNumber('report')->middleware('throttle:vote')->name('public.reports.vote');

// พยากรณ์และเรดาร์ฝน (โหลดข้อมูลแยก ไม่หน่วงหน้าหลัก)
Route::get('/{province}/water-map', [\App\Http\Controllers\Public\WaterMapController::class, 'index'])->where('province', '[a-z][a-z-]+')->name('public.water-map');
Route::get('/{province}/water/context.json', [\App\Http\Controllers\Public\WaterMapController::class, 'context'])->where('province', '[a-z][a-z-]+')->middleware('throttle:60,1')->name('public.water.context');
Route::get('/{province}/water/stations.json', [\App\Http\Controllers\Public\WaterMapController::class, 'data'])->where('province', '[a-z][a-z-]+')->middleware('throttle:30,1')->name('public.water.data');
Route::get('/{province}/water/reports.json', [\App\Http\Controllers\Public\WaterMapController::class, 'reports'])->where('province', '[a-z][a-z-]+')->middleware('throttle:60,1')->name('public.water.reports');
Route::get('/{province}/weather', [\App\Http\Controllers\Public\WeatherController::class, 'index'])->where('province', '[a-z][a-z-]+')->name('public.weather');
Route::get('/{province}/weather/context.json', [\App\Http\Controllers\Public\WeatherController::class, 'context'])->where('province', '[a-z][a-z-]+')->middleware('throttle:60,1')->name('public.weather.context');
Route::get('/{province}/weather/forecast.json', [\App\Http\Controllers\Public\WeatherController::class, 'forecast'])->where('province', '[a-z][a-z-]+')->middleware('throttle:30,1')->name('public.weather.forecast');
Route::get('/{province}/weather/radar.json', [\App\Http\Controllers\Public\WeatherController::class, 'radar'])->where('province', '[a-z][a-z-]+')->middleware('throttle:30,1')->name('public.weather.radar');

// เสนอจุดเสี่ยง
Route::get('/{province}/risks/propose', [RiskProposalController::class, 'form'])->where('province', '[a-z][a-z-]+')->name('public.risks.propose');
Route::post('/{province}/risks/propose', [RiskProposalController::class, 'store'])->where('province', '[a-z][a-z-]+')->middleware('throttle:5,10')->name('public.risks.store');
Route::get('/{province}', [PublicController::class, 'showProvince'])
    ->where('province', '[a-z][a-z-]+')
    ->name('public.province');
