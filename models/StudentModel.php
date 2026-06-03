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
}