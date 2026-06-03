<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/ClassModel.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/Response.php';

class ClassController
{
    private ClassModel $model;

    public function __construct()
    {
        $this->model = new ClassModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRoles(['Teacher', 'Department Head']);

        $authUser = AuthMiddleware::user();

        try {
            $classes = $this->model->getByTeacher((int) $authUser->id);
            Response::success(['classes' => $classes], 'Classes retrieved successfully.');
        } catch (Exception $e) {
            error_log('[ClassController::index] ' . $e->getMessage());
            Response::serverError('Failed to retrieve classes.');
        }
    }

    public function roster(int $teacherSubjectId): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRoles(['Teacher', 'Department Head']);

        try {
            $students = $this->model->getRoster($teacherSubjectId);
            Response::success(['students' => $students], 'Roster retrieved successfully.');
        } catch (Exception $e) {
            error_log('[ClassController::roster] ' . $e->getMessage());
            Response::serverError('Failed to retrieve roster.');
        }
    }
}
