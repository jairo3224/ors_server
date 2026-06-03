<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class ClassModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function getByTeacher(int $teacherId): array
    {
        $sql = "
            SELECT
                ts.id AS teacher_subject_id,
                s.id AS subject_id,
                s.subject_code,
                s.subject_name,
                sec.id AS section_id,
                sec.section_name,
                sec.year_level,
                sy.id AS school_year_id,
                sy.school_year,
                sy.semester
            FROM teacher_subjects ts
            JOIN subjects s ON ts.subject_id = s.id
            JOIN sections sec ON ts.section_id = sec.id
            JOIN school_years sy ON ts.school_year_id = sy.id
            WHERE ts.teacher_id = :teacher_id
              AND sy.is_active = 1
              AND s.deleted_at IS NULL
              AND sec.deleted_at IS NULL
            ORDER BY s.subject_name
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':teacher_id' => $teacherId]);
        return $stmt->fetchAll();
    }

    public function getRoster(int $teacherSubjectId): array
    {
        $sql = "
            SELECT
                st.id AS student_id,
                st.student_number,
                st.first_name,
                st.last_name,
                st.middle_name,
                st.year_level,
                st.department_id,
                d.department_name,
                sec.id AS section_id,
                sec.section_name,
                st.status
            FROM student_subjects ss
            JOIN students st ON ss.student_id = st.id
            JOIN sections sec ON ss.section_id = sec.id
            JOIN departments d ON d.id = st.department_id
            JOIN teacher_subjects ts ON ts.id = :teacher_subject_id
            WHERE ss.subject_id = ts.subject_id
              AND ss.section_id = ts.section_id
              AND ss.school_year_id = ts.school_year_id
              AND st.status = 'active'
              AND st.deleted_at IS NULL
            ORDER BY st.last_name, st.first_name
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':teacher_subject_id' => $teacherSubjectId]);
        return $stmt->fetchAll();
    }
}
