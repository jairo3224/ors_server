<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/StudentModel.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/Response.php';

class StudentController
{
    private StudentModel $model;

    public function __construct()
    {
        $this->model = new StudentModel();
    }

    public function search(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRoles(['Teacher', 'Department Head', 'OSAS', 'Guidance Office']);

        $keyword = trim($_GET['q'] ?? '');
        $limit   = min((int) ($_GET['limit'] ?? 20), 50);
        $offset  = max((int) ($_GET['offset'] ?? 0), 0);

        if ($keyword === '') {
            Response::success(['students' => []], 'No search term provided.');
        }

        try {
            $authUser = AuthMiddleware::user();
            $departmentId = $authUser->department_id ?? null;

            if ($departmentId !== null && in_array($authUser->role ?? '', ['Teacher', 'Department Head'], true)) {
                $students = $this->model->searchByKeywordWithDepartment($keyword, (int) $departmentId, $limit, $offset);
            } else {
                $students = $this->model->searchByKeyword($keyword, $limit, $offset);
            }

            Response::success(
                ['students' => $students],
                'Students retrieved successfully.'
            );
        } catch (Exception $e) {
            error_log('[StudentController::search] ' . $e->getMessage());
            Response::serverError('Failed to search students.');
        }
    }
}
