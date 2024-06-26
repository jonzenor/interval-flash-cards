<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\TestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes();

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::prefix('admin')->group(function () {
    Voyager::routes();
});

Route::get('/exams', [TestController::class, 'sets'])->name('tests')->middleware('auth');
Route::get('/exam/{id}', [TestController::class, 'select'])->name('select-test')->middleware('auth');
Route::post('/exam/{id}/start', [TestController::class, 'start'])->name('start-test')->middleware('auth');
Route::get('/test/{id}', [TestController::class, 'test'])->name('take-test')->middleware('auth');
Route::post('/test/{id}', [TestController::class, 'answer'])->name('answer')->middleware('auth');

Route::get('/history/{id}', [HomeController::class, 'history'])->name('test-history')->middleware('auth');

Route::get('/manage', [QuestionController::class, 'exams'])->name('manage-exams')->middleware('auth');
Route::get('/manage/{id}', [QuestionController::class, 'index'])->name('manage-questions')->middleware('auth');
Route::get('/manage/{id}/add', [QuestionController::class, 'add'])->name('add-question')->middleware('auth');
Route::post('/manage/{id}/add', [QuestionController::class, 'store'])->name('save-question')->middleware('auth');
Route::get('/manage/{id}/edit', [QuestionController::class, 'edit'])->name('edit-question')->middleware('auth');
Route::post('/manage/{id}/update', [QuestionController::class, 'update'])->name('update-question')->middleware('auth');

Route::get('/question/{id}', [QuestionController::class, 'answers'])->name('manage-answers')->middleware('auth');
Route::post('/question/{id}/add', [QuestionController::class, 'storeAnswer'])->name('save-answers')->middleware('auth');
Route::get('/answer/{id}/edit', [QuestionController::class, 'editAnswer'])->name('edit-answer')->middleware('auth');
Route::post('/answer/{id}/edit', [QuestionController::class, 'updateAnswer'])->name('update-answer')->middleware('auth');
Route::get('/answer/{id}/delete', [QuestionController::class, 'deleteAnswer'])->name('delete-answer')->middleware('auth');
Route::post('/answer/{id}/delete', [QuestionController::class, 'deleteAnswerConfirm'])->name('delete-answer-confirm')->middleware('auth');

Route::post('/exam/add', [QuestionController::class, 'storeExam'])->name('save-exam')->middleware('auth');
