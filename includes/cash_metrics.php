<?php
// /daily_closing/includes/cash_metrics.php

use PDO;

/**
 * Calculate cash on hand metrics for a manager scoped to optional outlets and date range.
 *
 * @param PDO   $pdo
 * @param int   $managerId
 * @param array $outletIds Optional array of outlet IDs to constrain the calculation.
 * @param array $options   Supported keys:
 *                         - 'date_from' (Y-m-d) inclusive lower bound
 *                         - 'date_to'   (Y-m-d) inclusive upper bound
 *                         - 'statuses'  array of submission statuses to include
 * @return array{pending: float, posted: float, income: float, expenses: float, pass_to_office: float}
 */
function manager_cash_on_hand(PDO $pdo, int $managerId, array $outletIds = [], array $options = []): array
{
    $where = ['s.manager_id = :manager_id'];
    $params = ['manager_id' => $managerId];

    if ($outletIds) {
        $in = [];
        foreach ($outletIds as $index => $outletId) {
            $placeholder = ':outlet_' . $index;
            $params[$placeholder] = (int)$outletId;
            $in[] = $placeholder;
        }
        $where[] = 's.outlet_id IN (' . implode(',', $in) . ')';
    }

    if (!empty($options['date_from'])) {
        $where[] = 's.date >= :date_from';
        $params['date_from'] = $options['date_from'];
    }

    if (!empty($options['date_to'])) {
        $where[] = 's.date <= :date_to';
        $params['date_to'] = $options['date_to'];
    }

    if (!empty($options['statuses']) && is_array($options['statuses'])) {
        $statuses = [];
        foreach ($options['statuses'] as $index => $status) {
            $status = (string)$status;
            if ($status === '') {
                continue;
            }
            $placeholder = ':status_' . $index;
            $params[$placeholder] = $status;
            $statuses[] = $placeholder;
        }
        if ($statuses) {
            $where[] = 's.status IN (' . implode(',', $statuses) . ')';
        }
    }

    $sql = '
        SELECT
            SUM(CASE WHEN s.status IN (\'approved\', \'recorded\') THEN (s.total_income - s.total_expenses - s.pass_to_office) ELSE 0 END) AS posted_delta,
            SUM(CASE WHEN s.status IN (\'pending\', \'submitted\', \'draft\') THEN (s.total_income - s.total_expenses - s.pass_to_office) ELSE 0 END) AS pending_delta,
            SUM(s.total_income) AS income_total,
            SUM(s.total_expenses) AS expenses_total,
            SUM(s.pass_to_office) AS pass_total
        FROM submissions s
        WHERE ' . implode(' AND ', $where) . '
    ';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $type);
    }
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'pending' => (float)($row['pending_delta'] ?? 0.0),
        'posted' => (float)($row['posted_delta'] ?? 0.0),
        'income' => (float)($row['income_total'] ?? 0.0),
        'expenses' => (float)($row['expenses_total'] ?? 0.0),
        'pass_to_office' => (float)($row['pass_total'] ?? 0.0),
    ];
}

/**
 * Convenience helper for validation — returns the posted cash on hand for a specific outlet.
 */
function outlet_posted_cash_on_hand(PDO $pdo, int $managerId, int $outletId): float
{
    $sql = '
        SELECT COALESCE(SUM(s.total_income - s.total_expenses - s.pass_to_office), 0)
        FROM submissions s
        WHERE s.manager_id = ? AND s.outlet_id = ? AND s.status IN (\'approved\', \'recorded\')
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$managerId, $outletId]);

    return (float)$stmt->fetchColumn();
}
