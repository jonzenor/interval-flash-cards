<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ExamSetController;
use App\Http\Controllers\PracticeController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\ExamSessionController;
use App\Models\CreditHistory;
use App\Models\Product;
use Laravel\Pennant\Feature;

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

$middleware = ['auth'];
$verify = false;

try {
    if (Feature::active('email_verification')) {
        $middleware[] = 'verified';
        $verify = true;
    }
} catch (\Illuminate\Database\QueryException $e) {
    // Features table may not exist during initial deployment
    // PostgreSQL: SQLSTATE 42P01, MySQL: SQLSTATE 42S02 or error code 1146
    $code = $e->getCode();
    $message = $e->getMessage();
    
    // Only suppress "table not found" errors, re-throw everything else
    $isTableNotFound = in_array($code, ['42P01', '42S02', 1146])
        || str_contains($message, 'does not exist')
        || str_contains($message, "doesn't exist");
    
    if (!$isTableNotFound) {
        throw $e;
    }
    // Default to not requiring email verification when table doesn't exist
}

Auth::routes(['verify' => true]);

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/privacy-policy', [HomeController::class, 'privacy'])->name('privacy-policy');
Route::get('/terms-of-service', [HomeController::class, 'tos'])->name('terms-of-service');
Route::get('/pricing', [HomeController::class, 'pricing'])->name('pricing');
Route::get('/checkout/{product}/{price}', [HomeController::class, 'checkout'])->name('checkout')->middleware($middleware);
Route::get('/purchase-success', [HomeController::class, 'success'])->name('purchase-success')->middleware($middleware);


Route::get('/myexams', [QuestionController::class, 'exams'])->name('manage-exams')->middleware($middleware);
Route::get('/exams', [ExamSetController::class, 'public'])->name('exam.public');

Route::get('/profile/exams', function () {
    return redirect()->route('profile.exams');
});

Route::prefix('exam')->name('exam.')->controller(ExamSetController::class)->middleware($middleware)->group(function () {
    Route::get('/', 'index')->name('index');

    Route::get('/architect', 'create')->name('create');
    Route::post('/store', 'store')->name('store');
    Route::post('/{exam}/update', 'update')->name('update');

    Route::get('/{set}', 'view')->name('view')->withoutMiddleware(['auth', 'verified']);
    Route::get('/{set}/edit', 'edit')->name('edit');
    Route::post('/{set}/add', 'add')->name('add');

    Route::get('/{exam}/question/{question}', 'question')->name('question');
    Route::post('/{exam}/question/{question}/update', 'questionUpdate')->name('questionUpdate');
    Route::post('/{exam}/question/{question}/addAnswer', 'addAnswer')->name('addAnswer');
    Route::get('/{exam}/question/{question}/delete', 'questionDelete')->name('questionDelete');
    Route::post('/{exam}/question/{question}/remove', 'questionRemove')->name('questionRemove');
});

// Route::prefix('boyager')->group(function () {
//     Voyager::routes();
// });

Route::prefix('profile')->name('profile.')->controller(ProfileController::class)->middleware($middleware)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/library', 'exams')->name('exams');
    Route::get('/author', 'myexams')->name('myexams');
    Route::post('/update', 'update')->name('update');
    Route::post('/changepass', 'changepass')->name('changepass');
    Route::get('/{user}/credits', 'credits')->name('credits');
    Route::post('/{user}/credits/gift', 'gift')->name('gift');
});

Route::get('/transcripts/{user}', [ProfileController::class, 'view'])->name('profile.view');

Route::prefix('test')->name('exam-session.')->controller(ExamSessionController::class)->middleware($middleware)->group(function () {
    Route::get('/{set}/start', 'start')->name('start');
    Route::get('/{set}/configure', 'configure')->name('configure');
    Route::post('/{set}/store', 'store')->name('store');
    Route::get('/{set}/test', 'test')->name('test');
    Route::post('/{set}/answer', 'answer')->name('answer');
    Route::get('/{set}/answer', 'answerRedirect')->name('answer-break');
    Route::get('/{set}/summary', 'summary')->name('summary');
    Route::get('/{set}/register', 'register')->name('register');
    Route::get('/{set}/enroll', 'enroll')->name('enroll');
    Route::get('/{set}/flagReview/{question}', 'toggleReviewFlag')->name('toggleReviewFlag');
});

Route::prefix('practice')->name('practice.')->controller(PracticeController::class)->middleware($middleware)->group(function () {
    Route::get('/{set}/start', 'start')->name('start');
    Route::get('/{set}/settings', 'settings')->name('settings');
    Route::post('/{set}/begin', 'begin')->name('begin');
    Route::get('/{set}/review', 'review')->name('review');
    Route::get('/{set}/next', 'next')->name('next');
    Route::get('/{set}/previous', 'previous')->name('previous');
    Route::get('/{set}/done', 'done')->name('done');
    Route::get('/{set}/flagReview/{question}', 'toggleReviewFlag')->name('toggleReviewFlag');
});

Route::prefix('admin')->name('admin.')->controller(AdminController::class)->middleware($middleware)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/users', 'users')->name('users');
    Route::get('/users/{user}', 'user')->name('user');
    Route::post('/users/{user}/update', 'userUpdate')->name('user-update');
    Route::post('/users/{user}/', 'gift')->name('gift');

    Route::prefix('products')->name('product.')->controller(ProductController::class)->middleware('auth')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/store', 'store')->name('store');
        Route::get('/edit/{product}', 'edit')->name('edit');
        Route::post('/update/{product}', 'update')->name('update');
    });
});

Route::get('/colors', [HomeController::class, 'colors'])->name('colors');

Route::get('/oldexam/{id}', [TestController::class, 'select'])->name('select-test')->middleware('auth');
Route::post('/oldexam/{id}/start', [TestController::class, 'start'])->name('start-test')->middleware('auth');
Route::get('/test/{id}', [TestController::class, 'test'])->name('take-test')->middleware('auth');
Route::post('/test/{id}', [TestController::class, 'answer'])->name('answer')->middleware('auth');

Route::get('/history/{id}', [HomeController::class, 'history'])->name('test-history')->middleware('auth');

Route::get('/manage/{id}/add', [QuestionController::class, 'add'])->name('add-question')->middleware('auth');
Route::post('/manage/{id}/add', [QuestionController::class, 'store'])->name('save-question')->middleware('auth');
Route::get('/manage/{id}/edit', [QuestionController::class, 'edit'])->name('edit-question')->middleware('auth');
Route::post('/manage/{id}/update', [QuestionController::class, 'update'])->name('update-question')->middleware('auth');
Route::post('/manage/{set}/storeGroup', [GroupController::class, 'store'])->name('group-store')->middleware('auth');

Route::get('/questionGroup/{group}', [GroupController::class, 'show'])->name('group-view')->middleware('auth');
Route::post('/questionGroup/{group}/update', [GroupController::class, 'update'])->name('group-update')->middleware('auth');
Route::post('/questionGroup/{group}/addQuestions', [GroupController::class, 'storeQuestions'])->name('group-store-questions')->middleware('auth');
Route::get('/questionGroup/{group}/editQuestion/{question}', [GroupController::class, 'editQuestion'])->name('group-edit-question')->middleware('auth');
Route::post('/questionGroup/{group}/updateQuestion/{question}', [GroupController::class, 'updateQuestion'])->name('group-update-question')->middleware('auth');
Route::get('/questionGroup/{group}/deleteQuestion/{question}', [GroupController::class, 'deleteQuestion'])->name('group-delete-question')->middleware('auth');
Route::post('/questionGroup/{group}/removeQuestion/{question}', [GroupController::class, 'removeQuestion'])->name('group-remove-question')->middleware('auth');

Route::get('/question/{id}', [QuestionController::class, 'answers'])->name('manage-answers')->middleware('auth');
Route::post('/question/{id}/add', [QuestionController::class, 'storeAnswer'])->name('save-answers')->middleware('auth');
Route::get('/answer/{id}/edit', [QuestionController::class, 'editAnswer'])->name('edit-answer')->middleware('auth');
Route::post('/answer/{id}/edit', [QuestionController::class, 'updateAnswer'])->name('update-answer')->middleware('auth');
Route::get('/answer/{id}/delete', [QuestionController::class, 'deleteAnswer'])->name('delete-answer')->middleware('auth');
Route::post('/answer/{id}/delete', [QuestionController::class, 'deleteAnswerConfirm'])->name('delete-answer-confirm')->middleware('auth');
