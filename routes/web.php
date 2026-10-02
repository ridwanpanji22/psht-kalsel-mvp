<?php

use App\Http\Controllers\ApprovalDecisionController;
use App\Http\Controllers\Auth\FirebaseAuthActivationController;
use App\Http\Controllers\Auth\FirebaseAuthSessionController;
use App\Http\Controllers\PrivateMediaController;
use App\Http\Controllers\Public\EventController;
use App\Http\Controllers\Public\EventCoverController;
use App\Http\Controllers\Public\GalleryController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\OrganizationController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\PublicMediaController;
use App\Http\Controllers\Public\TrainingLocationController;
use App\Http\Controllers\PublishedContentController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('public.home');
Route::get('/profil/{slug}', [PageController::class, 'show'])->name('public.pages.show');
Route::get('/struktur', [OrganizationController::class, 'index'])->name('public.organization.index');
Route::get('/galeri', [GalleryController::class, 'index'])->name('public.galleries.index');
Route::get('/media/{media}', PublicMediaController::class)->name('public.media.show');
Route::get('/kegiatan', [EventController::class, 'index'])->name('public.events.index');
Route::get('/kegiatan/{slug}', [EventController::class, 'show'])->name('public.events.show');
Route::get('/kegiatan/{event:slug}/cover', EventCoverController::class)->name('public.events.cover');
Route::get('/tempat-latihan', [TrainingLocationController::class, 'index'])->name('public.training-locations.index');
Route::get('/tempat-latihan/{slug}', [TrainingLocationController::class, 'show'])->name('public.training-locations.show');

Route::get('/admin/activate', fn () => view('auth.activate'))->name('auth.activate');

Route::post('/admin/auth/session', FirebaseAuthSessionController::class)
    ->middleware('throttle:5,1')
    ->name('auth.firebase.session');
Route::post('/admin/auth/activate', FirebaseAuthActivationController::class)
    ->middleware('throttle:5,1')
    ->name('auth.firebase.activate');

Route::post('/admin/pages/{page}/submit-for-review', [PublishedContentController::class, 'store'])
    ->middleware('auth')
    ->name('pages.submit-for-review');
Route::post('/admin/approval-requests/{approvalRequest}/decision', [ApprovalDecisionController::class, 'store'])
    ->middleware('auth')
    ->name('approval-requests.decision');
Route::get('/admin/media/{media}', [PrivateMediaController::class, 'show'])->name('media.show');
Route::post('/admin/media', [PrivateMediaController::class, 'store'])
    ->middleware('auth')
    ->name('media.store');
