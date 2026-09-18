<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\ActivityRegistrationController;
use App\Http\Controllers\Api\RegistrationAttendanceController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\OrganizationStructureController;
use App\Http\Controllers\Api\OrganizationProfileController;
use App\Http\Controllers\Api\UserManagementController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\OrganizationDocumentController;
use App\Http\Controllers\Api\OrganizationLetterController;

use App\Http\Controllers\Api\ElectionController;
use App\Http\Controllers\Api\ElectionCandidateController;
use App\Http\Controllers\Api\CandidateApplicationController;
use App\Http\Controllers\Api\CandidateEligibilityController;

/*
|--------------------------------------------------------------------------
| API ROUTES - E-ORGANISASI HIMATIF
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| FALLBACK LOGIN
|--------------------------------------------------------------------------
*/

Route::get('/login', function () {
    return response()->json([
        'success' => false,
        'message' => 'Anda belum login atau token tidak ditemukan.',
    ], 401);
})->name('login');

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post(
        '/login',
        [
            AuthController::class,
            'login'
        ]
    );
});

/*
|--------------------------------------------------------------------------
| DATA PUBLIK
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| DASHBOARD
|--------------------------------------------------------------------------
*/

Route::get(
    '/dashboard',
    [
        DashboardController::class,
        'index'
    ]
);

/*
|--------------------------------------------------------------------------
| KEGIATAN
|--------------------------------------------------------------------------
*/

Route::get(
    '/activities',
    [
        ActivityController::class,
        'index'
    ]
);

Route::get(
    '/activities/{id}',
    [
        ActivityController::class,
        'show'
    ]
)->whereNumber('id');

/*
|--------------------------------------------------------------------------
| PENGUMUMAN
|--------------------------------------------------------------------------
*/

Route::get(
    '/announcements',
    [
        AnnouncementController::class,
        'index'
    ]
);

Route::get(
    '/announcements/{id}',
    [
        AnnouncementController::class,
        'show'
    ]
)->whereNumber('id');

/*
|--------------------------------------------------------------------------
| STRUKTUR ORGANISASI
|--------------------------------------------------------------------------
*/

Route::get(
    '/organization-structure',
    [
        OrganizationStructureController::class,
        'index'
    ]
);

/*
|--------------------------------------------------------------------------
| PROFIL ORGANISASI
|--------------------------------------------------------------------------
*/

Route::get(
    '/organization-profile',
    [
        OrganizationProfileController::class,
        'show'
    ]
);

/*
|--------------------------------------------------------------------------
| ROUTE WAJIB LOGIN
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | ACCOUNT
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/me',
            [
                AuthController::class,
                'me'
            ]
        );

        Route::post(
            '/logout',
            [
                AuthController::class,
                'logout'
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | PROFILE USER
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/profile',
            [
                MemberController::class,
                'profile'
            ]
        );

        Route::put(
            '/profile',
            [
                MemberController::class,
                'updateProfile'
            ]
        );

        Route::patch(
            '/profile',
            [
                MemberController::class,
                'updateProfile'
            ]
        );

        Route::post(
            '/profile/photo',
            [
                MemberController::class,
                'uploadPhoto'
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | SERTIFIKAT USER LOGIN
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/my-certificates',
            [
                CertificateController::class,
                'myCertificates'
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | PEMILIHAN USER LOGIN
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/elections/active',
            [
                ElectionController::class,
                'active'
            ]
        );

        Route::get(
            '/elections/{id}',
            [
                ElectionController::class,
                'show'
            ]
        )->whereNumber('id');

        Route::get(
            '/elections/{electionId}/candidates',
            [
                ElectionCandidateController::class,
                'index'
            ]
        )->whereNumber('electionId');

        Route::get(
            '/elections/{id}/results',
            [
                ElectionController::class,
                'results'
            ]
        )->whereNumber('id');

        /*
        |--------------------------------------------------------------------------
        | ADMIN
        |--------------------------------------------------------------------------
        */

        Route::prefix('admin')
            ->middleware('role:admin')
            ->group(function () {

                /*
                |--------------------------------------------------------------------------
                | DASHBOARD ADMIN
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/dashboard',
                    [
                        DashboardController::class,
                        'admin'
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | KEUANGAN ADMIN
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/finances/summary',
                    [
                        FinanceController::class,
                        'summary'
                    ]
                );

                Route::get(
                    '/finances',
                    [
                        FinanceController::class,
                        'index'
                    ]
                );

                Route::post(
                    '/finances',
                    [
                        FinanceController::class,
                        'store'
                    ]
                );

                Route::get(
                    '/finances/{id}',
                    [
                        FinanceController::class,
                        'show'
                    ]
                )->whereNumber('id');

                Route::put(
                    '/finances/{id}',
                    [
                        FinanceController::class,
                        'update'
                    ]
                )->whereNumber('id');

                Route::patch(
                    '/finances/{id}',
                    [
                        FinanceController::class,
                        'update'
                    ]
                )->whereNumber('id');

                Route::delete(
                    '/finances/{id}',
                    [
                        FinanceController::class,
                        'destroy'
                    ]
                )->whereNumber('id');

                /*
                |--------------------------------------------------------------------------
                | DOKUMENTASI KEGIATAN ADMIN
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/documents/summary',
                    [
                        OrganizationDocumentController::class,
                        'summary'
                    ]
                );

                Route::get(
                    '/documents',
                    [
                        OrganizationDocumentController::class,
                        'index'
                    ]
                );

                Route::post(
                    '/documents',
                    [
                        OrganizationDocumentController::class,
                        'store'
                    ]
                );

                Route::get(
                    '/documents/{id}',
                    [
                        OrganizationDocumentController::class,
                        'show'
                    ]
                )->whereNumber('id');

                Route::put(
                    '/documents/{id}',
                    [
                        OrganizationDocumentController::class,
                        'update'
                    ]
                )->whereNumber('id');

                Route::patch(
                    '/documents/{id}',
                    [
                        OrganizationDocumentController::class,
                        'update'
                    ]
                )->whereNumber('id');

                Route::delete(
                    '/documents/{id}',
                    [
                        OrganizationDocumentController::class,
                        'destroy'
                    ]
                )->whereNumber('id');

                /*
                |--------------------------------------------------------------------------
                | ARSIP SURAT ADMIN
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/letters/summary',
                    [
                        OrganizationLetterController::class,
                        'summary'
                    ]
                );

                Route::get(
                    '/letters',
                    [
                        OrganizationLetterController::class,
                        'index'
                    ]
                );

                Route::post(
                    '/letters',
                    [
                        OrganizationLetterController::class,
                        'store'
                    ]
                );

                Route::get(
                    '/letters/{id}',
                    [
                        OrganizationLetterController::class,
                        'show'
                    ]
                )->whereNumber('id');

                Route::put(
                    '/letters/{id}',
                    [
                        OrganizationLetterController::class,
                        'update'
                    ]
                )->whereNumber('id');

                Route::patch(
                    '/letters/{id}',
                    [
                        OrganizationLetterController::class,
                        'update'
                    ]
                )->whereNumber('id');

                Route::delete(
                    '/letters/{id}',
                    [
                        OrganizationLetterController::class,
                        'destroy'
                    ]
                )->whereNumber('id');

                /*
                |--------------------------------------------------------------------------
                | USER MANAGEMENT
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/users',
                    [
                        UserManagementController::class,
                        'index'
                    ]
                );

                Route::post(
                    '/users',
                    [
                        UserManagementController::class,
                        'store'
                    ]
                );

                Route::get(
                    '/users/{id}',
                    [
                        UserManagementController::class,
                        'show'
                    ]
                )->whereNumber('id');

                Route::put(
                    '/users/{id}',
                    [
                        UserManagementController::class,
                        'update'
                    ]
                )->whereNumber('id');

                Route::patch(
                    '/users/{id}',
                    [
                        UserManagementController::class,
                        'update'
                    ]
                )->whereNumber('id');

                Route::patch(
                    '/users/{id}/activate',
                    [
                        UserManagementController::class,
                        'activate'
                    ]
                )->whereNumber('id');

                Route::patch(
                    '/users/{id}/deactivate',
                    [
                        UserManagementController::class,
                        'deactivate'
                    ]
                )->whereNumber('id');

                Route::delete(
                    '/users/{id}',
                    [
                        UserManagementController::class,
                        'destroy'
                    ]
                )->whereNumber('id');

                /*
                |--------------------------------------------------------------------------
                | MEMBER
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/members',
                    [
                        MemberController::class,
                        'index'
                    ]
                );

                Route::post(
                    '/members',
                    [
                        MemberController::class,
                        'store'
                    ]
                );

                Route::get(
                    '/members/{id}',
                    [
                        MemberController::class,
                        'show'
                    ]
                )->whereNumber('id');

                Route::put(
                    '/members/{id}',
                    [
                        MemberController::class,
                        'update'
                    ]
                )->whereNumber('id');

                Route::patch(
                    '/members/{id}',
                    [
                        MemberController::class,
                        'update'
                    ]
                )->whereNumber('id');

                Route::delete(
                    '/members/{id}',
                    [
                        MemberController::class,
                        'destroy'
                    ]
                )->whereNumber('id');

                /*
                |--------------------------------------------------------------------------
                | KEGIATAN ADMIN
                |--------------------------------------------------------------------------
                */

                Route::post(
                    '/activities',
                    [
                        ActivityController::class,
                        'store'
                    ]
                );

                Route::put(
                    '/activities/{id}',
                    [
                        ActivityController::class,
                        'update'
                    ]
                )->whereNumber('id');

                Route::patch(
                    '/activities/{id}',
                    [
                        ActivityController::class,
                        'update'
                    ]
                )->whereNumber('id');

                Route::patch(
                    '/activities/{id}/status',
                    [
                        ActivityController::class,
                        'updateStatus'
                    ]
                )->whereNumber('id');

                Route::delete(
                    '/activities/{id}',
                    [
                        ActivityController::class,
                        'destroy'
                    ]
                )->whereNumber('id');

                /*
                |--------------------------------------------------------------------------
                | PESERTA KEGIATAN ADMIN
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/activities/{id}/participants',
                    [
                        ActivityRegistrationController::class,
                        'participants'
                    ]
                )->whereNumber('id');

                /*
                |--------------------------------------------------------------------------
                | DAFTAR HADIR ADMIN
                |--------------------------------------------------------------------------
                |
                | GET
                | Mengambil peserta terdaftar beserta status absensi.
                |
                | POST
                | Menyimpan status:
                | present / absent / unrecorded.
                |
                */

                Route::get(
                    '/activities/{activityId}/attendance',
                    [
                        RegistrationAttendanceController::class,
                        'attendanceParticipants'
                    ]
                )->whereNumber('activityId');

                Route::post(
                    '/activities/{activityId}/attendance',
                    [
                        RegistrationAttendanceController::class,
                        'saveAttendance'
                    ]
                )->whereNumber('activityId');

                /*
                |--------------------------------------------------------------------------
                | SERTIFIKAT ADMIN
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/activities/{id}/certificates',
                    [
                        CertificateController::class,
                        'participants'
                    ]
                )->whereNumber('id');

                Route::post(
                    '/activities/{activityId}/certificate-template',
                    [
                        CertificateController::class,
                        'uploadTemplate'
                    ]
                )->whereNumber('activityId');

                Route::post(
                    '/activities/{activityId}/certificates/generate',
                    [
                        CertificateController::class,
                        'generateAll'
                    ]
                )->whereNumber('activityId');

                Route::post(
                    '/activities/{activityId}/certificates/{userId}',
                    [
                        CertificateController::class,
                        'issue'
                    ]
                )
                    ->whereNumber('activityId')
                    ->whereNumber('userId');

                Route::post(
                    '/certificates/{certificateId}/upload',
                    [
                        CertificateController::class,
                        'uploadFile'
                    ]
                )->whereNumber('certificateId');

                Route::patch(
                    '/certificates/{certificateId}/revoke',
                    [
                        CertificateController::class,
                        'revoke'
                    ]
                )->whereNumber('certificateId');

                /*
                |--------------------------------------------------------------------------
                | PENGUMUMAN ADMIN
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/announcements',
                    [
                        AnnouncementController::class,
                        'index'
                    ]
                );

                Route::post(
                    '/announcements',
                    [
                        AnnouncementController::class,
                        'store'
                    ]
                );

                Route::get(
                    '/announcements/{id}',
                    [
                        AnnouncementController::class,
                        'show'
                    ]
                )->whereNumber('id');

                Route::put(
                    '/announcements/{id}',
                    [
                        AnnouncementController::class,
                        'update'
                    ]
                )->whereNumber('id');

                Route::patch(
                    '/announcements/{id}',
                    [
                        AnnouncementController::class,
                        'update'
                    ]
                )->whereNumber('id');

                Route::delete(
                    '/announcements/{id}',
                    [
                        AnnouncementController::class,
                        'destroy'
                    ]
                )->whereNumber('id');

                /*
                |--------------------------------------------------------------------------
                | PROFIL ORGANISASI ADMIN
                |--------------------------------------------------------------------------
                */

                Route::put(
                    '/organization-profile',
                    [
                        OrganizationProfileController::class,
                        'update'
                    ]
                );

                Route::patch(
                    '/organization-profile',
                    [
                        OrganizationProfileController::class,
                        'update'
                    ]
                );

                Route::post(
                    '/organization-profile/photo',
                    [
                        OrganizationProfileController::class,
                        'uploadPhoto'
                    ]
                );

                Route::delete(
                    '/organization-profile/photo',
                    [
                        OrganizationProfileController::class,
                        'deletePhoto'
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | PEMILIHAN ADMIN
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/elections',
                    [
                        ElectionController::class,
                        'index'
                    ]
                );

                Route::post(
                    '/elections',
                    [
                        ElectionController::class,
                        'store'
                    ]
                );

                Route::get(
                    '/elections/{id}',
                    [
                        ElectionController::class,
                        'show'
                    ]
                )->whereNumber('id');

                Route::put(
                    '/elections/{id}',
                    [
                        ElectionController::class,
                        'update'
                    ]
                )->whereNumber('id');

                Route::patch(
                    '/elections/{id}',
                    [
                        ElectionController::class,
                        'update'
                    ]
                )->whereNumber('id');

                Route::delete(
                    '/elections/{id}',
                    [
                        ElectionController::class,
                        'destroy'
                    ]
                )->whereNumber('id');

                Route::patch(
                    '/elections/{id}/status',
                    [
                        ElectionController::class,
                        'updateStatus'
                    ]
                )->whereNumber('id');

                /*
                |--------------------------------------------------------------------------
                | KELAYAKAN KANDIDAT
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/elections/{electionId}/candidate-eligibility',
                    [
                        CandidateEligibilityController::class,
                        'index'
                    ]
                )->whereNumber('electionId');

                /*
                |--------------------------------------------------------------------------
                | KANDIDAT
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/elections/{electionId}/candidates',
                    [
                        ElectionCandidateController::class,
                        'index'
                    ]
                )->whereNumber('electionId');

                Route::post(
                    '/elections/{electionId}/candidates',
                    [
                        ElectionCandidateController::class,
                        'store'
                    ]
                )->whereNumber('electionId');

                Route::put(
                    '/elections/{electionId}/candidates/{candidateId}',
                    [
                        ElectionCandidateController::class,
                        'update'
                    ]
                )
                    ->whereNumber('electionId')
                    ->whereNumber('candidateId');

                Route::patch(
                    '/elections/{electionId}/candidates/{candidateId}',
                    [
                        ElectionCandidateController::class,
                        'update'
                    ]
                )
                    ->whereNumber('electionId')
                    ->whereNumber('candidateId');

                Route::delete(
                    '/elections/{electionId}/candidates/{candidateId}',
                    [
                        ElectionCandidateController::class,
                        'destroy'
                    ]
                )
                    ->whereNumber('electionId')
                    ->whereNumber('candidateId');

                /*
                |--------------------------------------------------------------------------
                | PENGAJUAN KANDIDAT
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/elections/{electionId}/candidate-applications',
                    [
                        CandidateApplicationController::class,
                        'index'
                    ]
                )->whereNumber('electionId');

                Route::post(
                    '/elections/{electionId}/candidate-applications/{applicationId}/approve',
                    [
                        CandidateApplicationController::class,
                        'approve'
                    ]
                )
                    ->whereNumber('electionId')
                    ->whereNumber('applicationId');

                Route::post(
                    '/elections/{electionId}/candidate-applications/{applicationId}/reject',
                    [
                        CandidateApplicationController::class,
                        'reject'
                    ]
                )
                    ->whereNumber('electionId')
                    ->whereNumber('applicationId');

                /*
                |--------------------------------------------------------------------------
                | PEMILIH
                |--------------------------------------------------------------------------
                */

                Route::post(
                    '/elections/{id}/sync-voters',
                    [
                        ElectionController::class,
                        'syncVoters'
                    ]
                )->whereNumber('id');

                Route::get(
                    '/elections/{id}/voters',
                    [
                        ElectionController::class,
                        'voters'
                    ]
                )->whereNumber('id');

                /*
                |--------------------------------------------------------------------------
                | BUKA / TUTUP VOTING
                |--------------------------------------------------------------------------
                */

                Route::post(
                    '/elections/{id}/open-voting',
                    [
                        ElectionController::class,
                        'openVoting'
                    ]
                )->whereNumber('id');

                Route::post(
                    '/elections/{id}/close-voting',
                    [
                        ElectionController::class,
                        'closeVoting'
                    ]
                )->whereNumber('id');

                /*
                |--------------------------------------------------------------------------
                | HASIL PEMILIHAN
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/elections/{id}/results',
                    [
                        ElectionController::class,
                        'results'
                    ]
                )->whereNumber('id');
            });

        /*
        |--------------------------------------------------------------------------
        | PENGURUS
        |--------------------------------------------------------------------------
        */

        Route::prefix('officer')
            ->middleware('role:pengurus')
            ->group(function () {

                /*
                |--------------------------------------------------------------------------
                | DASHBOARD PENGURUS
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/dashboard',
                    [
                        DashboardController::class,
                        'officer'
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | KEUANGAN PENGURUS
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/finances/summary',
                    [
                        FinanceController::class,
                        'summary'
                    ]
                );

                Route::get(
                    '/finances',
                    [
                        FinanceController::class,
                        'index'
                    ]
                );

                Route::get(
                    '/finances/{id}',
                    [
                        FinanceController::class,
                        'show'
                    ]
                )->whereNumber('id');

                /*
                |--------------------------------------------------------------------------
                | DOKUMENTASI PENGURUS
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/documents/summary',
                    [
                        OrganizationDocumentController::class,
                        'summary'
                    ]
                );

                Route::get(
                    '/documents',
                    [
                        OrganizationDocumentController::class,
                        'index'
                    ]
                );

                Route::get(
                    '/documents/{id}',
                    [
                        OrganizationDocumentController::class,
                        'show'
                    ]
                )->whereNumber('id');

                /*
                |--------------------------------------------------------------------------
                | ARSIP SURAT PENGURUS
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/letters/summary',
                    [
                        OrganizationLetterController::class,
                        'summary'
                    ]
                );

                Route::get(
                    '/letters',
                    [
                        OrganizationLetterController::class,
                        'index'
                    ]
                );

                Route::get(
                    '/letters/{id}',
                    [
                        OrganizationLetterController::class,
                        'show'
                    ]
                )->whereNumber('id');

                /*
                |--------------------------------------------------------------------------
                | KEGIATAN PENGURUS
                |--------------------------------------------------------------------------
                */

                Route::post(
                    '/activities',
                    [
                        ActivityController::class,
                        'store'
                    ]
                );

                Route::put(
                    '/activities/{id}',
                    [
                        ActivityController::class,
                        'update'
                    ]
                )->whereNumber('id');

                Route::patch(
                    '/activities/{id}',
                    [
                        ActivityController::class,
                        'update'
                    ]
                )->whereNumber('id');

                Route::delete(
                    '/activities/{id}',
                    [
                        ActivityController::class,
                        'destroy'
                    ]
                )->whereNumber('id');

                /*
                |--------------------------------------------------------------------------
                | PESERTA PENGURUS
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/activities/{id}/participants',
                    [
                        ActivityRegistrationController::class,
                        'participants'
                    ]
                )->whereNumber('id');

                /*
                |--------------------------------------------------------------------------
                | DAFTAR HADIR PENGURUS
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/activities/{activityId}/attendance',
                    [
                        RegistrationAttendanceController::class,
                        'attendanceParticipants'
                    ]
                )->whereNumber('activityId');

                Route::post(
                    '/activities/{activityId}/attendance',
                    [
                        RegistrationAttendanceController::class,
                        'saveAttendance'
                    ]
                )->whereNumber('activityId');

                /*
                |--------------------------------------------------------------------------
                | SERTIFIKAT PENGURUS
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/activities/{id}/certificates',
                    [
                        CertificateController::class,
                        'participants'
                    ]
                )->whereNumber('id');

                Route::post(
                    '/activities/{activityId}/certificate-template',
                    [
                        CertificateController::class,
                        'uploadTemplate'
                    ]
                )->whereNumber('activityId');

                Route::post(
                    '/activities/{activityId}/certificates/generate',
                    [
                        CertificateController::class,
                        'generateAll'
                    ]
                )->whereNumber('activityId');

                Route::post(
                    '/activities/{activityId}/certificates/{userId}',
                    [
                        CertificateController::class,
                        'issue'
                    ]
                )
                    ->whereNumber('activityId')
                    ->whereNumber('userId');

                Route::post(
                    '/certificates/{certificateId}/upload',
                    [
                        CertificateController::class,
                        'uploadFile'
                    ]
                )->whereNumber('certificateId');

                Route::patch(
                    '/certificates/{certificateId}/revoke',
                    [
                        CertificateController::class,
                        'revoke'
                    ]
                )->whereNumber('certificateId');

                /*
                |--------------------------------------------------------------------------
                | PENGUMUMAN PENGURUS
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/announcements',
                    [
                        AnnouncementController::class,
                        'index'
                    ]
                );

                Route::post(
                    '/announcements',
                    [
                        AnnouncementController::class,
                        'store'
                    ]
                );

                Route::get(
                    '/announcements/{id}',
                    [
                        AnnouncementController::class,
                        'show'
                    ]
                )->whereNumber('id');

                Route::put(
                    '/announcements/{id}',
                    [
                        AnnouncementController::class,
                        'update'
                    ]
                )->whereNumber('id');

                Route::patch(
                    '/announcements/{id}',
                    [
                        AnnouncementController::class,
                        'update'
                    ]
                )->whereNumber('id');

                Route::delete(
                    '/announcements/{id}',
                    [
                        AnnouncementController::class,
                        'destroy'
                    ]
                )->whereNumber('id');

                /*
                |--------------------------------------------------------------------------
                | PROFIL ORGANISASI PENGURUS
                |--------------------------------------------------------------------------
                */

                Route::put(
                    '/organization-profile',
                    [
                        OrganizationProfileController::class,
                        'update'
                    ]
                );

                Route::patch(
                    '/organization-profile',
                    [
                        OrganizationProfileController::class,
                        'update'
                    ]
                );

                Route::post(
                    '/organization-profile/photo',
                    [
                        OrganizationProfileController::class,
                        'uploadPhoto'
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | PEMILIHAN PENGURUS
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/elections',
                    [
                        ElectionController::class,
                        'index'
                    ]
                );

                Route::get(
                    '/elections/active',
                    [
                        ElectionController::class,
                        'active'
                    ]
                );

                Route::get(
                    '/elections/{id}',
                    [
                        ElectionController::class,
                        'show'
                    ]
                )->whereNumber('id');

                Route::get(
                    '/elections/{electionId}/candidates',
                    [
                        ElectionCandidateController::class,
                        'index'
                    ]
                )->whereNumber('electionId');

                Route::get(
                    '/elections/{electionId}/eligibility',
                    [
                        CandidateEligibilityController::class,
                        'myEligibility'
                    ]
                )->whereNumber('electionId');

                Route::get(
                    '/elections/{electionId}/candidate-application',
                    [
                        CandidateApplicationController::class,
                        'myApplication'
                    ]
                )->whereNumber('electionId');

                Route::post(
                    '/elections/{electionId}/candidate-application',
                    [
                        CandidateApplicationController::class,
                        'store'
                    ]
                )->whereNumber('electionId');

                Route::put(
                    '/elections/{electionId}/candidate-application',
                    [
                        CandidateApplicationController::class,
                        'update'
                    ]
                )->whereNumber('electionId');

                Route::patch(
                    '/elections/{electionId}/candidate-application',
                    [
                        CandidateApplicationController::class,
                        'update'
                    ]
                )->whereNumber('electionId');

                Route::delete(
                    '/elections/{electionId}/candidate-application',
                    [
                        CandidateApplicationController::class,
                        'destroy'
                    ]
                )->whereNumber('electionId');

                Route::get(
                    '/elections/{id}/voting-status',
                    [
                        ElectionController::class,
                        'votingStatus'
                    ]
                )->whereNumber('id');

                Route::get(
                    '/elections/{id}/my-status',
                    [
                        ElectionController::class,
                        'myVotingStatus'
                    ]
                )->whereNumber('id');

                Route::post(
                    '/elections/{id}/vote',
                    [
                        ElectionController::class,
                        'vote'
                    ]
                )->whereNumber('id');

                Route::get(
                    '/elections/{id}/results',
                    [
                        ElectionController::class,
                        'results'
                    ]
                )->whereNumber('id');
            });

        /*
        |--------------------------------------------------------------------------
        | MAHASISWA
        |--------------------------------------------------------------------------
        */

        Route::prefix('student')
            ->middleware('role:mahasiswa')
            ->group(function () {

                /*
                |--------------------------------------------------------------------------
                | DASHBOARD MAHASISWA
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/dashboard',
                    [
                        DashboardController::class,
                        'student'
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | DOKUMENTASI MAHASISWA
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/documents/summary',
                    [
                        OrganizationDocumentController::class,
                        'summary'
                    ]
                );

                Route::get(
                    '/documents',
                    [
                        OrganizationDocumentController::class,
                        'index'
                    ]
                );

                Route::get(
                    '/documents/{id}',
                    [
                        OrganizationDocumentController::class,
                        'show'
                    ]
                )->whereNumber('id');

                /*
                |--------------------------------------------------------------------------
                | PENDAFTARAN KEGIATAN
                |--------------------------------------------------------------------------
                */

                Route::post(
                    '/activities/{id}/register',
                    [
                        ActivityRegistrationController::class,
                        'register'
                    ]
                )->whereNumber('id');

                Route::delete(
                    '/activities/{id}/register',
                    [
                        ActivityRegistrationController::class,
                        'cancel'
                    ]
                )->whereNumber('id');

                Route::get(
                    '/activities/{id}/registration-status',
                    [
                        ActivityRegistrationController::class,
                        'status'
                    ]
                )->whereNumber('id');

                Route::get(
                    '/my-activities',
                    [
                        ActivityRegistrationController::class,
                        'myRegistrations'
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | SERTIFIKAT MAHASISWA
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/certificates',
                    [
                        CertificateController::class,
                        'myCertificates'
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | PENGUMUMAN
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/announcements',
                    [
                        AnnouncementController::class,
                        'index'
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | PEMILIHAN MAHASISWA
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/elections',
                    [
                        ElectionController::class,
                        'index'
                    ]
                );

                Route::get(
                    '/elections/active',
                    [
                        ElectionController::class,
                        'active'
                    ]
                );

                Route::get(
                    '/elections/{id}',
                    [
                        ElectionController::class,
                        'show'
                    ]
                )->whereNumber('id');

                Route::get(
                    '/elections/{electionId}/candidates',
                    [
                        ElectionCandidateController::class,
                        'index'
                    ]
                )->whereNumber('electionId');

                Route::get(
                    '/elections/{electionId}/eligibility',
                    [
                        CandidateEligibilityController::class,
                        'myEligibility'
                    ]
                )->whereNumber('electionId');

                Route::get(
                    '/elections/{electionId}/candidate-application',
                    [
                        CandidateApplicationController::class,
                        'myApplication'
                    ]
                )->whereNumber('electionId');

                Route::post(
                    '/elections/{electionId}/candidate-application',
                    [
                        CandidateApplicationController::class,
                        'store'
                    ]
                )->whereNumber('electionId');

                Route::put(
                    '/elections/{electionId}/candidate-application',
                    [
                        CandidateApplicationController::class,
                        'update'
                    ]
                )->whereNumber('electionId');

                Route::patch(
                    '/elections/{electionId}/candidate-application',
                    [
                        CandidateApplicationController::class,
                        'update'
                    ]
                )->whereNumber('electionId');

                Route::delete(
                    '/elections/{electionId}/candidate-application',
                    [
                        CandidateApplicationController::class,
                        'destroy'
                    ]
                )->whereNumber('electionId');

                Route::get(
                    '/elections/{id}/voting-status',
                    [
                        ElectionController::class,
                        'votingStatus'
                    ]
                )->whereNumber('id');

                Route::get(
                    '/elections/{id}/my-status',
                    [
                        ElectionController::class,
                        'myVotingStatus'
                    ]
                )->whereNumber('id');

                Route::post(
                    '/elections/{id}/vote',
                    [
                        ElectionController::class,
                        'vote'
                    ]
                )->whereNumber('id');

                Route::get(
                    '/elections/{id}/results',
                    [
                        ElectionController::class,
                        'results'
                    ]
                )->whereNumber('id');
            });
    });