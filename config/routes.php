<?php
/** @var Router $router */

// ---- Public site --------------------------------------------------
$router->get('/',                     [HomeController::class,     'index']);
$router->get('/about',                [HomeController::class,     'about']);
$router->get('/contact',              [HomeController::class,     'contact']);
$router->post('/api/contact',         [HomeController::class,     'submitContact']);

// ---- Summer school ------------------------------------------------
$router->get('/summer',               [SummerController::class,   'index']);
$router->post('/api/summer/register', [SummerController::class,   'register']);
$router->get('/summer/track/{slug}',  [SummerController::class,   'track']);

// ---- Academy (LMS catalog) ----------------------------------------
$router->get('/academy',              [AcademyController::class,  'index']);
$router->get('/academy/{slug}',       [AcademyController::class,  'course']);
$router->post('/api/enroll',          [AcademyController::class,  'enroll']);

// ---- Learner auth + dashboard -------------------------------------
$router->get('/login',                [AuthController::class,     'showLogin']);
$router->post('/login',               [AuthController::class,     'login']);
$router->post('/logout',              [AuthController::class,     'logout']);
$router->get('/dashboard',            [StudentController::class,  'index']);

// ---- Legal --------------------------------------------------------
$router->get('/legal/privacy',        [HomeController::class,     'privacy']);
$router->get('/legal/terms',          [HomeController::class,     'terms']);

// ---- SEO ----------------------------------------------------------
$router->get('/sitemap.xml',          [HomeController::class,     'sitemap']);
$router->get('/robots.txt',           [HomeController::class,     'robots']);

// ==== Operator console (RBAC) ======================================
$router->get('/admin/login',          ['Admin\AuthController',       'showLogin']);
$router->post('/admin/login',         ['Admin\AuthController',       'login']);
$router->post('/admin/logout',        ['Admin\AuthController',       'logout']);

$router->get('/admin',                ['Admin\DashboardController',  'index']);

// Summer registrations management
$router->get('/admin/summer',              ['Admin\SummerController', 'index']);
$router->get('/admin/summer/export.csv',   ['Admin\SummerController', 'export']);
$router->get('/admin/summer/{id}',         ['Admin\SummerController', 'show']);
$router->post('/admin/summer/{id}/status', ['Admin\SummerController', 'updateStatus']);
$router->post('/admin/summer/{id}/payment',['Admin\SummerController', 'updatePayment']);

// Courses
$router->get('/admin/courses',            ['Admin\CoursesController', 'index']);
$router->get('/admin/courses/new',        ['Admin\CoursesController', 'edit']);
$router->get('/admin/courses/{id}/edit',  ['Admin\CoursesController', 'edit']);
$router->post('/admin/courses/save',      ['Admin\CoursesController', 'save']);
$router->post('/admin/courses/{id}/delete',['Admin\CoursesController','delete']);

// Students
$router->get('/admin/students',           ['Admin\StudentsController', 'index']);
$router->post('/admin/students/{id}/status',['Admin\StudentsController','status']);

// Landing content
$router->get('/admin/content',            ['Admin\ContentController', 'index']);
$router->post('/admin/content/save',      ['Admin\ContentController', 'save']);

// Operators (RBAC user management)
$router->get('/admin/users',              ['Admin\UsersController', 'index']);
$router->get('/admin/users/new',          ['Admin\UsersController', 'create']);
$router->post('/admin/users/save',        ['Admin\UsersController', 'store']);
$router->post('/admin/users/{id}/role',   ['Admin\UsersController', 'updateRole']);
$router->post('/admin/users/{id}/status', ['Admin\UsersController', 'updateStatus']);
$router->post('/admin/users/{id}/delete', ['Admin\UsersController', 'delete']);
