<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class StudentModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Get all active students for a given department.
     * Returns student info, year level, section name, program (department name),
     * and counts of related incidents.
     */
    public function findByDepartment(int $departmentId): array
    {
        $sql = "
            SELECT
                s.id,
                s.student_number,
                s.first_name,
                s.last_name,
                s.middle_name,
                s.year_level,
                s.status,
                d.department_name AS program,
                sec.section_name,
                COUNT(ir.id) AS cases_count
            FROM students s
            INNER JOIN departments d ON d.id = s.department_id
            LEFT JOIN sections sec ON sec.id = s.section_id
            LEFT JOIN incident_reports ir ON ir.student_id = s.id AND ir.deleted_at IS NULL
            WHERE s.department_id = :dept_id
              AND s.deleted_at IS NULL
            GROUP BY s.id
            ORDER BY s.last_name, s.first_name
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':dept_id' => $departmentId]);
        return $stmt->fetchAll();
    }

    /**
     * Return all active students (dev helper).
     */
    public function findAll(): array
    {
        $sql = "
            SELECT
                s.id,
                s.student_number,
                s.first_name,
                s.last_name,
                s.middle_name,
                s.year_level,
                s.status,
                d.department_name AS program,
                sec.section_name,
                COUNT(ir.id) AS cases_count
            FROM students s
            INNER JOIN departments d ON d.id = s.department_id
            LEFT JOIN sections sec ON sec.id = s.section_id
            LEFT JOIN incident_reports ir ON ir.student_id = s.id AND ir.deleted_at IS NULL
            WHERE s.deleted_at IS NULL
            GROUP BY s.id
            ORDER BY s.last_name, s.first_name
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function searchByKeyword(string $keyword, int $limit = 20, int $offset = 0): array
    {
        $like = '%' . $keyword . '%';

        $sql = "
            SELECT
                s.id,
                s.student_number,
                s.first_name,
                s.last_name,
                s.middle_name,
                s.year_level,
                s.status,
                d.department_name,
                sec.section_name
            FROM students s
            LEFT JOIN departments d ON s.department_id = d.id
            LEFT JOIN sections sec ON s.section_id = sec.id
            WHERE s.deleted_at IS NULL
              AND s.status = 'active'
              AND (
                  s.first_name  LIKE :keyword
                  OR s.last_name   LIKE :keyword2
                  OR s.student_number LIKE :keyword3
              )
            ORDER BY s.last_name, s.first_name
            LIMIT :lim OFFSET :off
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':keyword', $like, PDO::PARAM_STR);
        $stmt->bindValue(':keyword2', $like, PDO::PARAM_STR);
        $stmt->bindValue(':keyword3', $like, PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function searchByKeywordWithDepartment(string $keyword, int $departmentId, int $limit = 20, int $offset = 0): array
    {
        $like = '%' . $keyword . '%';

        $sql = "
            SELECT
                s.id,
                s.student_number,
                s.first_name,
                s.last_name,
                s.middle_name,
                s.year_level,
                s.status,
                d.department_name,
                sec.section_name
            FROM students s
            LEFT JOIN departments d ON s.department_id = d.id
            LEFT JOIN sections sec ON s.section_id = sec.id
            WHERE s.deleted_at IS NULL
              AND s.status = 'active'
              AND s.department_id = :dept_id
              AND (
                  s.first_name  LIKE :keyword
                  OR s.last_name   LIKE :keyword2
                  OR s.student_number LIKE :keyword3
              )
            ORDER BY s.last_name, s.first_name
            LIMIT :lim OFFSET :off
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':dept_id', $departmentId, PDO::PARAM_INT);
        $stmt->bindValue(':keyword', $like, PDO::PARAM_STR);
        $stmt->bindValue(':keyword2', $like, PDO::PARAM_STR);
        $stmt->bindValue(':keyword3', $like, PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
