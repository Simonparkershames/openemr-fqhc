<?php

/**
 * Database boundary for the provider's results inbox (issue #38): reports
 * that have come back for tests this provider ordered and are still pending
 * review (`procedure_report.review_status = 'received'`). Read-only; the
 * review/sign-off happens on the certified procedures screen.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Provider;

use OpenEMR\Common\Database\QueryUtils;

final readonly class ResultReviewRepository
{
    /**
     * Reports awaiting this provider's review, most recently reported first.
     *
     * @return list<ResultToReview>
     */
    public function pendingReviewForProvider(int $providerId, int $limit = 8): array
    {
        if ($providerId <= 0) {
            return [];
        }

        $limit = max(1, $limit);
        $records = QueryUtils::fetchRecords(
            'SELECT prpt.procedure_report_id, po.patient_id, po.date_ordered, '
            . 'prpt.date_report, pd.fname, pd.lname, '
            . 'COALESCE(NULLIF(poc.procedure_order_title, \'\'), poc.procedure_name, \'Result\') AS test_name, '
            . 'EXISTS (SELECT 1 FROM procedure_result pr '
            . 'WHERE pr.procedure_report_id = prpt.procedure_report_id '
            . "AND pr.abnormal NOT IN ('', 'no')) AS abnormal "
            . 'FROM procedure_report prpt '
            . 'JOIN procedure_order po ON po.procedure_order_id = prpt.procedure_order_id '
            . 'JOIN patient_data pd ON pd.pid = po.patient_id '
            . 'LEFT JOIN procedure_order_code poc '
            . 'ON poc.procedure_order_id = po.procedure_order_id '
            . 'AND poc.procedure_order_seq = prpt.procedure_order_seq '
            . "WHERE po.provider_id = ? AND po.activity = 1 AND prpt.review_status = 'received' "
            . 'ORDER BY prpt.date_report DESC, prpt.procedure_report_id DESC '
            . 'LIMIT ' . $limit,
            [$providerId],
        );

        $results = [];
        foreach ($records as $record) {
            $pid = $record['patient_id'] ?? null;
            if (!is_numeric($pid)) {
                continue;
            }

            $results[] = new ResultToReview(
                (int) $pid,
                $this->formatName($record['lname'] ?? null, $record['fname'] ?? null, (int) $pid),
                $this->stringOrDefault($record['test_name'] ?? null, 'Result'),
                $this->formatDate($record['date_report'] ?? $record['date_ordered'] ?? null),
                $this->isTruthy($record['abnormal'] ?? null),
            );
        }

        return $results;
    }

    private function formatName(mixed $last, mixed $first, int $pid): string
    {
        $last = is_string($last) ? trim($last) : '';
        $first = is_string($first) ? trim($first) : '';
        if ($last === '' && $first === '') {
            return 'Patient #' . $pid;
        }
        if ($last === '' || $first === '') {
            return $last . $first;
        }

        return $last . ', ' . $first;
    }

    private function formatDate(mixed $datetime): string
    {
        if (!is_string($datetime) || $datetime === '') {
            return '';
        }
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return '';
        }

        return date('M j, Y', $timestamp);
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        return is_string($value) && trim($value) !== '' ? $value : $default;
    }

    private function isTruthy(mixed $value): bool
    {
        return is_numeric($value) && (int) $value === 1;
    }
}
