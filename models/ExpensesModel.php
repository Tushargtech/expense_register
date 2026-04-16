<?php

class ExpensesModel
{
    private $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    public function getExpenses(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $offset = ($page - 1) * $perPage;

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = "(r.request_title LIKE ? OR r.request_description LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['type'])) {
            $where[] = "r.request_type = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['department'])) {
            $where[] = "r.department_id = ?";
            $params[] = $filters['department'];
        }

        if (!empty($filters['status'])) {
            $where[] = "r.request_status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "r.request_submitted_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = "r.request_submitted_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        // For RBAC, assume user can see their own or all if admin
        $userId = $_SESSION['auth']['user_id'] ?? 0;
        $userRole = $_SESSION['auth']['role'] ?? '';
        if ($userRole !== 'admin') {
            $where[] = "r.request_submitted_by = ?";
            $params[] = $userId;
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT r.*, d.department_name, u.user_name as submitter_name, u.user_email as submitter_email
                FROM requests r
                LEFT JOIN departments d ON r.department_id = d.id
                LEFT JOIN users u ON r.request_submitted_by = u.user_id
                WHERE $whereClause
                ORDER BY r.request_submitted_at DESC
                LIMIT $perPage OFFSET $offset";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM requests r WHERE $whereClause";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        return [
            'expenses' => $expenses,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }
}