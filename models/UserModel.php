<?php

// models/UserModel.php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class UserModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Find a user by email, joining role and department.
     * Excludes soft-deleted and inactive users.
     */
    public function findByEmail(string $email): ?array
    {
        $sql = "
            SELECT
                u.id,
                u.employee_id,
                u.first_name,
                u.last_name,
                u.email,
                u.password,
                u.is_active,
                u.role_id,
                r.role_name,
                u.department_id,
                d.department_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            LEFT  JOIN departments d ON d.id = u.department_id
            WHERE u.email = :email
              AND u.deleted_at IS NULL
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);

        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Find a user by ID (for /me endpoint and token refresh).
     */
    public function findById(int $id): ?array
    {
        $sql = "
            SELECT
                u.id,
                u.employee_id,
                u.first_name,
                u.last_name,
                u.email,
                u.is_active,
                u.role_id,
                r.role_name,
                u.department_id,
                d.department_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            LEFT  JOIN departments d ON d.id = u.department_id
            WHERE u.id = :id
              AND u.deleted_at IS NULL
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findByRoleName(string $roleName): ?array
    {
        $sql = "
            SELECT
                u.id,
                u.employee_id,
                u.first_name,
                u.last_name,
                u.email,
                u.is_active,
                u.role_id,
                r.role_name,
                u.department_id,
                d.department_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            LEFT  JOIN departments d ON d.id = u.department_id
            WHERE r.role_name = :role_name
              AND u.deleted_at IS NULL
              AND u.is_active = 1
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':role_name' => $roleName]);

        $user = $stmt->fetch();
        return $user ?: null;
    }
}
