<?php

class LookupModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    public function getRoleSlugs(): array
    {
        $stmt = $this->db->query("SELECT role_slug FROM roles WHERE role_slug IN ('admin', 'finance', 'hr', 'employee') ORDER BY role_slug ASC");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

        return $this->normalizeUniqueStringList($rows, static fn(string $value): string => strtolower($value));
    }

    public function getRequestTypes(): array
    {
        $enumValues = $this->getEnumValues('requests', 'request_type');
        if ($enumValues !== []) {
            return $this->normalizeUniqueStringList($enumValues, static fn(string $value): string => strtolower($value));
        }

        return $this->getBudgetCategoryTypes();
    }

    public function getRequestPriorities(): array
    {
        $enumValues = $this->getEnumValues('requests', 'request_priority');

        return $this->normalizeUniqueStringList($enumValues, static fn(string $value): string => strtolower($value));
    }

    public function getRequestCurrencies(): array
    {
        $values = [];

        $queries = [
            'SELECT DISTINCT request_currency AS currency FROM requests WHERE request_currency IS NOT NULL AND request_currency <> ""',
            'SELECT DISTINCT budget_currency AS currency FROM department_budgets WHERE budget_currency IS NOT NULL AND budget_currency <> ""',
        ];

        foreach ($queries as $sql) {
            $stmt = $this->db->query($sql);
            if (!$stmt) {
                continue;
            }

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $values[] = (string) ($row['currency'] ?? '');
            }
        }

        return $this->normalizeUniqueStringList($values, static fn(string $value): string => strtoupper($value));
    }

    public function getBudgetCategoryTypes(): array
    {
        $stmt = $this->db->query('SELECT DISTINCT budget_category_type FROM budget_categories WHERE budget_category_type IS NOT NULL AND budget_category_type <> "" ORDER BY budget_category_type ASC');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

        $types = $this->normalizeUniqueStringList($rows, static fn(string $value): string => strtolower($value));
        $normalizedTypes = array_map(fn(string $value): string => $this->normalizeWorkflowType($value), $types);
        $filteredTypes = array_values(array_filter(array_unique($normalizedTypes), static fn(string $type): bool => in_array($type, ['expense', 'purchase'], true)));
        if ($filteredTypes !== []) {
            return $filteredTypes;
        }

        return ['expense', 'purchase'];
    }

    public function getWorkflowTypes(): array
    {
        $stmt = $this->db->query('SELECT DISTINCT workflow_type FROM workflows WHERE workflow_type IS NOT NULL AND workflow_type <> "" ORDER BY workflow_type ASC');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

        $normalizedWorkflowTypes = $this->normalizeUniqueStringList($rows, fn(string $value): string => $this->normalizeWorkflowType($value));
        $requestTypes = $this->normalizeUniqueStringList($this->getRequestTypes(), fn(string $value): string => $this->normalizeWorkflowType($value));
        $categoryTypes = $this->normalizeUniqueStringList($this->getBudgetCategoryTypes(), fn(string $value): string => $this->normalizeWorkflowType($value));

        return $this->normalizeUniqueStringList(
            array_merge($requestTypes, $categoryTypes, $normalizedWorkflowTypes),
            static fn(string $value): string => ucfirst(strtolower($value))
        );
    }

    private function normalizeWorkflowType(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace('_', ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        $aliases = [
            'expense' => 'expense',
            'expnse' => 'expense',
            'expence' => 'expense',
            'exponse' => 'expense',
            'purchase' => 'purchase',
            'puchase' => 'purchase',
            'purchse' => 'purchase',
            'prchase' => 'purchase',
        ];
        if (isset($aliases[$normalized])) {
            return $aliases[$normalized];
        }

        return $normalized;
    }

    private function getEnumValues(string $tableName, string $columnName): array
    {
        $sql = 'SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':table_name' => $tableName,
            ':column_name' => $columnName,
        ]);

        $columnType = (string) ($stmt->fetchColumn() ?: '');
        if (!str_starts_with(strtolower($columnType), 'enum(')) {
            return [];
        }

        $inner = substr($columnType, 5, -1);
        if ($inner === false || $inner === '') {
            return [];
        }

        $values = str_getcsv($inner, ',', "'", '\\');

        return $this->normalizeUniqueStringList($values, static fn(string $value): string => trim($value));
    }

    /**
     * @param array<int, mixed> $rawValues
     * @param callable(string): string $normalizer
     * @return array<int, string>
     */
    private function normalizeUniqueStringList(array $rawValues, callable $normalizer): array
    {
        $result = [];
        foreach ($rawValues as $rawValue) {
            $value = trim((string) $rawValue);
            if ($value === '') {
                continue;
            }

            $normalized = trim((string) $normalizer($value));
            if ($normalized === '') {
                continue;
            }

            $result[$normalized] = $normalized;
        }

        return array_values($result);
    }
}
