<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\AuthVerificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Subscription\PackageController;
use App\Http\Controllers\Subscription\SubscriptionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Teacher\TeacherLecture\TeacherLectureController;
use App\Http\Controllers\Teacher\TeacherSubscription\TeacherController;
use App\Http\Controllers\Teacher\TeacherSubscription\TeacherSubscriptionController;
use App\Http\Controllers\LessonsController;
use App\Http\Controllers\Student\StudentLectureController;
use App\Http\Middleware\CheckSubscription;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Teacher\Assignment\TeacherAssignmentController;
use App\Http\Controllers\Teacher\UserManagement\UserManagementController;
use App\Http\Controllers\AIAssistantController;
use App\Http\Controllers\Student\StudentAssignmentController;
use App\Http\Controllers\NotificationController;

/*
|--------------------------------------------------------------------------
| Public Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/


// Authentication routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail']);
Route::post('/reset-password', [ResetPasswordController::class, 'reset']);

// Email verification routes
Route::get('/email/verify/{id}/{hash}', [AuthVerificationController::class, 'verify'])
    ->name('verification.verify');
Route::post('/email/resend-verification', [AuthVerificationController::class, 'resend'])
    ->name('verification.send');

// Public packages listing
Route::get('/packages', [PackageController::class, 'index']);

// Public leaderboard
Route::get('/leaderboard', [DashboardController::class, 'topStudentsLeaderboard']);


// Admin routes
Route::post('/admin/login', [AuthController::class, 'adminLogin'])
    ->middleware(\App\Http\Middleware\CheckRole::class . ':teacher,assistant');

Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckRole::class . ':teacher,assistant'])->group(function () {
    Route::post('/admin/change-password', [AuthController::class, 'changePassword']);
});

Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckRole::class . ':student,assistant'])->group(function () {
    Route::put('/user/update', [AuthController::class, 'updateUser']);
});


// Admin login
Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'adminLogin']);

    // Other admin routes protected by teacher role
    Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckRole::class . ':teacher'])->group(function () {
        // Add your admin routes here
    });
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Authentication Required)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    // Common routes for all authenticated users
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Profile management
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::post('/password', [ProfileController::class, 'updatePassword']);
        Route::post('/photo', [ProfileController::class, 'updatePhoto']);
        Route::delete('/photo', [ProfileController::class, 'deletePhoto']);
    });
});


// payment routes
Route::prefix('payments')->group(function () {
    // Protected routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/initiate', [PaymentController::class, 'initiate']);
        Route::get('/history', [PaymentController::class, 'history']);
    });

    // Public callback routes
    Route::post('/verify', [PaymentController::class, 'verify']);
    Route::post('/callback', [PaymentController::class, 'callback']);
    Route::get('/callback-redirect', [PaymentController::class, 'callbackRedirect']);

    // Payment info
    Route::get('/{id}', [PaymentController::class, 'getPayment']);
});


// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {

    // Subscriptions
    Route::prefix('subscriptions')->group(function () {
        Route::post('/purchase', [SubscriptionController::class, 'purchase']);
        Route::get('/current', [SubscriptionController::class, 'currentSubscription']);
        Route::post('/renew', [SubscriptionController::class, 'renewSubscription']);
    });


    /*
    |--------------------------------------------------------------------------
    | Teacher Routes (Admin)
    |--------------------------------------------------------------------------
    */

    // Routes shared between teachers and assistants
    Route::middleware(\App\Http\Middleware\CheckRole::class . ':teacher,assistant')->prefix('admin')->group(function () {
        Route::post('/change-password', [AuthController::class, 'changePassword']);

        // Dashboard (except teacher dashboard)
        Route::prefix('dashboard')->group(function () {
            Route::get('/teacher/lectures', [DashboardController::class, 'scheduledLectures']);
            Route::get('/teacher/exams', [DashboardController::class, 'createdExams']);
            Route::get('/teacher/performance', [DashboardController::class, 'studentsPerformance']);
        });

        //package
        Route::apiResource('packages', PackageController::class);

        //lectures
        Route::apiResource('lectures', TeacherLectureController::class);

        // Assignments
        Route::apiResource('assignments', TeacherAssignmentController::class);

        // submissions for a specific assignment
        Route::get('/assignments/{assignment}/submissions', [TeacherAssignmentController::class, 'submissions']);
    });

    // Teacher-only routes
    Route::middleware(\App\Http\Middleware\CheckRole::class . ':teacher')->prefix('admin')->group(function () {
        // Teacher-specific dashboard
        Route::get('/dashboard/teacher', [DashboardController::class, 'teacherDashboard']);

        // Subscription management
        Route::prefix("subscription")->controller(TeacherSubscriptionController::class)
            ->group(function () {
                Route::get("/", "index");
                Route::get("/export", "export");
            });
    });

    /*
    |--------------------------------------------------------------------------
    | Student Routes
    |--------------------------------------------------------------------------
    */
    // Dashboard
    Route::middleware(\App\Http\Middleware\CheckRole::class . ':student', CheckSubscription::class)->prefix('dashboard')->group(function () {
        Route::get('/student', [DashboardController::class, 'studentDashboard']);
        Route::get('/student/packages', [DashboardController::class, 'enrolledPackages']);
        Route::get('/student/lectures', [DashboardController::class, 'upcomingLectures']);
        Route::get('/student/exams', [DashboardController::class, 'upcomingExams']);
        Route::get('/student/activities', [DashboardController::class, 'recentActivities']);
    });


    // Student routes with subscription check
    Route::middleware(['auth:sanctum', CheckSubscription::class])->prefix('student')->group(function () {
        // lectures routes
        Route::get('lectures', [StudentLectureController::class, 'index']);
        Route::get('lectures/{lecture}', [StudentLectureController::class, 'show']);

        // assignments routes
        Route::prefix('assignments')->group(function () {
            //  List assignments for the student's grade
            Route::get('/', [StudentAssignmentController::class, 'index']);
            //  Show details of a specific assignment before submission
            Route::get('/{assignmentId}', [StudentAssignmentController::class, 'show']);
            // Submit answers
            Route::post('/{assignmentId}/submit', [StudentAssignmentController::class, 'submit']);
        });

        // Submission routes
        Route::prefix('submissions')->group(function () {
            // List all past submissions for the student
            Route::get('/', [StudentAssignmentController::class, 'listSubmissions']);
            // Show detailed results of a specific submission
            Route::get('/{submissionId}', [StudentAssignmentController::class, 'showSubmission']);
        });
    });





    /*
    |--------------------------------------------------------------------------
    | Assistant Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware(\App\Http\Middleware\CheckRole::class . ':assistant')->prefix('dashboard')->group(function () {
        Route::get('/assistant', [DashboardController::class, 'assistantDashboard']);
        Route::get('/assistant/tasks', [DashboardController::class, 'assignedTasks']);
        Route::get('/assistant/inquiries', [DashboardController::class, 'studentInquiries']);
    });

    // Shared routes between student and assistant
    Route::middleware(\App\Http\Middleware\CheckRole::class . ':student,assistant')->group(function () {
        Route::put('/user/update', [AuthController::class, 'updateUser']);
    });
});

/*
|--------------------------------------------------------------------------
| Lesson Routes
|--------------------------------------------------------------------------
*/
// Admin routes (for teachers and assistants)
Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
    // Full CRUD access for teachers and assistants
    Route::apiResource('lessons', LessonsController::class);
});
// Protected routes
Route::prefix('lessons')->middleware(['auth:sanctum', CheckSubscription::class])->group(function () {
    Route::get('/', [LessonsController::class, 'index']);
    Route::get('/{id}', [LessonsController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| Material Routes
|--------------------------------------------------------------------------
*/

// Admin routes (for teachers and assistants)
Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckRole::class . ':teacher,assistant'])->prefix('admin')->group(function () {
    // Full CRUD access for teachers and assistants
    Route::apiResource('materials', MaterialController::class);
    Route::get('lessons/{lesson}/materials', [MaterialController::class, 'getMaterialsByLesson']);
});

// Student routes - read-only access
Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckRole::class . ':student'])->group(function () {
    Route::get('materials', [MaterialController::class, 'index']);
    Route::get('materials/{id}', [MaterialController::class, 'show']);
    Route::get('lessons/{lesson}/materials', [MaterialController::class, 'getMaterialsByLesson']);
});

/*
|--------------------------------------------------------------------------
| Admin User Management Routes
|--------------------------------------------------------------------------
*/

// Admin User Management Routes
Route::group(['middleware' => ['auth:sanctum', \App\Http\Middleware\CheckRole::class . ':teacher']], function () {
    Route::prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');
        Route::get('/{user}', [UserManagementController::class, 'show'])->name('show');
        Route::put('/{user}', [UserManagementController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserManagementController::class, 'destroy'])->name('destroy');
        Route::patch('/{user}/status', [UserManagementController::class, 'updateStatus'])->name('update-status');
        Route::patch('/{user}/role', [UserManagementController::class, 'updateRole'])->name('update-role');
        Route::patch('/{user}/grade', [UserManagementController::class, 'updateGrade'])->name('update-grade');
    });
});

/*
|--------------------------------------------------------------------------
| AI Assistant Routes
|--------------------------------------------------------------------------
*/

Route::prefix('ai-assistant')->group(function () {
    Route::post('/get-response', [AIAssistantController::class, 'getResponse']);
    Route::get('/help-topics', [AIAssistantController::class, 'getHelpTopics']);
});

/*
|--------------------------------------------------------------------------
| Notification Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    // Student notification routes
    Route::get('/notifications/student', [NotificationController::class, 'getStudentNotifications']);

    // Teacher notification routes
    Route::get('/notifications/teacher', [NotificationController::class, 'getTeacherNotifications']);

    // Common notification routes
    Route::post('/notifications/mark-read/{notification}', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
});
