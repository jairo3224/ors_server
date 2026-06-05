<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class GuidanceModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Get referrals sent TO the Guidance Office user(s).
     */
    public function getInbox(int $guidanceUserId): array
    {
        $sql = "
            SELECT
                r.id,
                r.incident_id,
                r.status,
                r.remarks AS response,
                r.responded_at,
                r.referred_at AS date_sent,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                s.id AS student_id,
                CONCAT(reporter.first_name, ' ', reporter.last_name) AS from_name,
                srcRole.role_name AS from_office,
                it.type_name AS subject,
                ir.description
            FROM referrals r
            INNER JOIN incident_reports ir ON ir.id = r.incident_id
            INNER JOIN students s ON s.id = ir.student_id
            INNER JOIN users referredBy ON referredBy.id = r.referred_by
            INNER JOIN roles srcRole ON srcRole.id = referredBy.role_id
            LEFT JOIN incident_types it ON it.id = ir.incident_type_id
            LEFT JOIN users reporter ON reporter.id = ir.reported_by
            WHERE r.referred_to = :guidance_user_id
            ORDER BY r.referred_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':guidance_user_id' => $guidanceUserId]);
        return $stmt->fetchAll();
    }

    /**
     * Get referrals sent BY the Guidance Office user(s).
     */
    public function getSent(int $guidanceUserId): array
    {
        $sql = "
            SELECT
                r.id,
                r.incident_id,
                r.status,
                r.remarks AS response,
                r.responded_at,
                r.referred_at AS date_sent,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                s.id AS student_id,
                CONCAT(targetUser.first_name, ' ', targetUser.last_name) AS to_name,
                tgtRole.role_name AS to_office,
                it.type_name AS subject,
                ir.description
            FROM referrals r
            INNER JOIN incident_reports ir ON ir.id = r.incident_id
            INNER JOIN students s ON s.id = ir.student_id
            INNER JOIN users targetUser ON targetUser.id = r.referred_to
            INNER JOIN roles tgtRole ON tgtRole.id = targetUser.role_id
            LEFT JOIN incident_types it ON it.id = ir.incident_type_id
            WHERE r.referred_by = :guidance_user_id
            ORDER BY r.referred_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':guidance_user_id' => $guidanceUserId]);
        return $stmt->fetchAll();
    }

    /**
     * Get incidents assigned to the Guidance Office user.
     */
    public function getAssignedIncidents(int $guidanceUserId): array
    {
        $sql = "
            SELECT
                ir.id,
                ir.report_code,
                ir.student_id,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                s.student_number,
                it.type_name AS type,
                ir.urgency_level,
                ir.current_status AS status,
                ir.description,
                ir.created_at AS date_reported,
                ir.updated_at AS last_updated,
                ir.resolved_at,
                CONCAT(reporter.first_name, ' ', reporter.last_name) AS teacher_name
            FROM incident_reports ir
            INNER JOIN students s ON s.id = ir.student_id
            LEFT JOIN incident_types it ON it.id = ir.incident_type_id
            LEFT JOIN users reporter ON reporter.id = ir.reported_by
            WHERE ir.assigned_to = :guidance_user_id
              AND ir.deleted_at IS NULL
            ORDER BY ir.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':guidance_user_id' => $guidanceUserId]);
        return $stmt->fetchAll();
    }

    /**
     * Get ALL incidents (for cross-office views).
     */
    public function getAllIncidents(): array
    {
        $sql = "
            SELECT
                ir.id,
                ir.report_code,
                ir.student_id,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                s.student_number,
                it.type_name AS type,
                ir.urgency_level,
                ir.current_status AS status,
                ir.description,
                ir.created_at AS date_reported,
                ir.updated_at AS last_updated,
                CONCAT(reporter.first_name, ' ', reporter.last_name) AS teacher_name,
                CONCAT(assigned.first_name, ' ', assigned.last_name) AS assigned_to_name
            FROM incident_reports ir
            INNER JOIN students s ON s.id = ir.student_id
            LEFT JOIN incident_types it ON it.id = ir.incident_type_id
            LEFT JOIN users reporter ON reporter.id = ir.reported_by
            LEFT JOIN users assigned ON assigned.id = ir.assigned_to
            WHERE ir.deleted_at IS NULL
            ORDER BY ir.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get responses (counseling notes, assessments, etc.) by the Guidance user.
     */
    public function getResponsesByUser(int $userId): array
    {
        $sql = "
            SELECT
                resp.id,
                resp.incident_id,
                resp.remarks,
                resp.created_at AS date,
                rt.type_name AS response_type,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                ir.description
            FROM responses resp
            INNER JOIN response_types rt ON rt.id = resp.response_type_id
            INNER JOIN incident_reports ir ON ir.id = resp.incident_id
            INNER JOIN students s ON s.id = ir.student_id
            WHERE resp.user_id = :user_id
            ORDER BY resp.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Get notifications for a specific user.
     */
    public function getNotifications(int $userId): array
    {
        $sql = "
            SELECT
                id,
                title,
                message,
                link,
                is_read AS `read`,
                created_at
            FROM notifications
            WHERE user_id = :user_id
            ORDER BY created_at DESC
            LIMIT 50
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Get student history: incidents, referrals, responses.
     */
    public function getStudentHistory(int $studentId): array
    {
        $incidents = $this->getStudentIncidents($studentId);
        $referrals = $this->getStudentReferrals($studentId);
        $responses = $this->getStudentResponses($studentId);

        return [
            'incidents' => $incidents,
            'referrals' => $referrals,
            'responses' => $responses,
        ];
    }

    private function getStudentIncidents(int $studentId): array
    {
        $sql = "
            SELECT
                ir.id,
                it.type_name AS type,
                ir.urgency_level,
                ir.current_status AS status,
                ir.description,
                ir.created_at AS date_reported,
                CONCAT(assigned.first_name, ' ', assigned.last_name) AS assigned_to
            FROM incident_reports ir
            LEFT JOIN incident_types it ON it.id = ir.incident_type_id
            LEFT JOIN users assigned ON assigned.id = ir.assigned_to
            WHERE ir.student_id = :student_id
              AND ir.deleted_at IS NULL
            ORDER BY ir.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':student_id' => $studentId]);
        return $stmt->fetchAll();
    }

    private function getStudentReferrals(int $studentId): array
    {
        $sql = "
            SELECT
                r.id,
                r.status,
                r.remarks AS response,
                r.responded_at,
                r.referred_at AS date_sent,
                it.type_name AS subject,
                ir.description,
                CONCAT(sender.first_name, ' ', sender.last_name) AS from_name,
                senderRole.role_name AS from_office,
                CONCAT(receiver.first_name, ' ', receiver.last_name) AS to_name,
                receiverRole.role_name AS to_office
            FROM referrals r
            INNER JOIN incident_reports ir ON ir.id = r.incident_id
            INNER JOIN users sender ON sender.id = r.referred_by
            INNER JOIN roles senderRole ON senderRole.id = sender.role_id
            INNER JOIN users receiver ON receiver.id = r.referred_to
            INNER JOIN roles receiverRole ON receiverRole.id = receiver.role_id
            LEFT JOIN incident_types it ON it.id = ir.incident_type_id
            WHERE ir.student_id = :student_id
            ORDER BY r.referred_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':student_id' => $studentId]);
        return $stmt->fetchAll();
    }

    private function getStudentResponses(int $studentId): array
    {
        $sql = "
            SELECT
                resp.id,
                resp.remarks,
                resp.created_at AS date,
                rt.type_name AS response_type,
                CONCAT(u.first_name, ' ', u.last_name) AS author
            FROM responses resp
            INNER JOIN response_types rt ON rt.id = resp.response_type_id
            INNER JOIN incident_reports ir ON ir.id = resp.incident_id
            INNER JOIN users u ON u.id = resp.user_id
            WHERE ir.student_id = :student_id
            ORDER BY resp.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':student_id' => $studentId]);
        return $stmt->fetchAll();
    }

    /**
     * Get dashboard stats for Guidance Office.
     */
    public function getOverviewStats(int $guidanceUserId): array
    {
        $pendingReferrals = $this->db->prepare("
            SELECT COUNT(*) FROM referrals
            WHERE referred_to = :user_id AND status = 'pending'
        ");
        $pendingReferrals->execute([':user_id' => $guidanceUserId]);

        $openCases = $this->db->prepare("
            SELECT COUNT(*) FROM incident_reports
            WHERE assigned_to = :user_id
              AND current_status IN ('under_review', 'in_progress', 'referred')
              AND deleted_at IS NULL
        ");
        $openCases->execute([':user_id' => $guidanceUserId]);

        $totalReferrals = $this->db->prepare("
            SELECT COUNT(*) FROM referrals
            WHERE referred_to = :user_id
        ");
        $totalReferrals->execute([':user_id' => $guidanceUserId]);

        $recentResponses = $this->db->prepare("
            SELECT COUNT(*) FROM responses
            WHERE user_id = :user_id
              AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $recentResponses->execute([':user_id' => $guidanceUserId]);

        return [
            'pending_referrals'  => (int) $pendingReferrals->fetchColumn(),
            'open_cases'         => (int) $openCases->fetchColumn(),
            'upcoming_sessions'  => (int) $recentResponses->fetchColumn(),
            'total_referrals'    => (int) $totalReferrals->fetchColumn(),
        ];
    }

    /**
     * Find a Guidance Office user by role.
     */
    public static function findGuidanceUserId(): ?int
    {
        $db = Database::connect();
        $sql = "
            SELECT u.id FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE r.role_name = 'Guidance Office'
              AND u.is_active = 1
              AND u.deleted_at IS NULL
            LIMIT 1
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? (int) $row['id'] : null;
    }
}
