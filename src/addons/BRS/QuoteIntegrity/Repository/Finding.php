<?php

namespace BRS\QuoteIntegrity\Repository;

class Finding
{
    protected $app;

    public function __construct(\XF\App $app)
    {
        $this->app = $app;
    }

    public function record(array $finding, string $source = 'live'): bool
    {
        $db = $this->app->db();
        $hash = hash('sha256', (string)$finding['normalized_url'], true);

        try
        {
            $db->insert('xf_brs_quote_integrity', [
                'post_id' => (int)$finding['post_id'],
                'thread_id' => (int)$finding['thread_id'],
                'user_id' => (int)$finding['user_id'],
                'username' => (string)$finding['username'],
                'quoted_post_id' => (int)$finding['quoted_post_id'],
                'quoted_user_id' => (int)$finding['quoted_user_id'],
                'quoted_username' => (string)$finding['quoted_username'],
                'detected_date' => time(),
                'added_url' => (string)$finding['added_url'],
                'added_domain' => (string)$finding['added_domain'],
                'url_hash' => $hash,
                'source' => $source === 'historical' ? 'historical' : 'live',
                'status' => 'open',
                'status_date' => 0,
                'status_user_id' => 0
            ]);

            return true;
        }
        catch (\XF\Db\DuplicateKeyException $e)
        {
            $existing = $db->fetchRow("
                SELECT finding_id, status
                FROM xf_brs_quote_integrity
                WHERE post_id = ?
                    AND quoted_post_id = ?
                    AND url_hash = ?
            ", [
                (int)$finding['post_id'],
                (int)$finding['quoted_post_id'],
                $hash
            ]);

            if ($existing && $existing['status'] === 'resolved')
            {
                $db->update(
                    'xf_brs_quote_integrity',
                    [
                        'status' => 'open',
                        'status_date' => 0,
                        'status_user_id' => 0,
                        'detected_date' => time()
                    ],
                    'finding_id = ?',
                    (int)$existing['finding_id']
                );
            }

            return false;
        }
    }

    public function recordMany(array $findings, string $source = 'live'): int
    {
        $count = 0;

        foreach ($findings as $finding)
        {
            if ($this->record($finding, $source))
            {
                $count++;
            }
        }

        return $count;
    }

    public function syncPostFindings(
        int $postId,
        array $findings,
        string $source = 'live'
    ): int
    {
        $db = $this->app->db();

        $currentKeys = [];

        foreach ($findings as $finding)
        {
            $hash = hash('sha256', (string)$finding['normalized_url'], true);

            $currentKeys[] = [
                'quoted_post_id' => (int)$finding['quoted_post_id'],
                'url_hash' => $hash
            ];
        }

        $existingRows = $db->fetchAll("
            SELECT finding_id, quoted_post_id, url_hash, status
            FROM xf_brs_quote_integrity
            WHERE post_id = ?
                AND status = 'open'
        ", $postId);

        foreach ($existingRows as $existing)
        {
            $stillExists = false;

            foreach ($currentKeys as $current)
            {
                if (
                    (int)$existing['quoted_post_id'] === $current['quoted_post_id']
                    && hash_equals($existing['url_hash'], $current['url_hash'])
                )
                {
                    $stillExists = true;
                    break;
                }
            }

            if (!$stillExists)
            {
                $db->update(
                    'xf_brs_quote_integrity',
                    [
                        'status' => 'resolved',
                        'status_date' => time(),
                        'status_user_id' => 0
                    ],
                    'finding_id = ?',
                    (int)$existing['finding_id']
                );
            }
        }

        return $this->recordMany($findings, $source);
    }

    public function ignore(int $findingId, int $userId): bool
    {
        $affected = $this->app->db()->update(
            'xf_brs_quote_integrity',
            [
                'status' => 'ignored',
                'status_date' => time(),
                'status_user_id' => $userId
            ],
            "finding_id = ? AND status = 'open'",
            $findingId
        );

        return $affected > 0;
    }

    public function unignore(int $findingId): bool
    {
        $affected = $this->app->db()->update(
            'xf_brs_quote_integrity',
            [
                'status' => 'open',
                'status_date' => 0,
                'status_user_id' => 0
            ],
            "finding_id = ? AND status = 'ignored'",
            $findingId
        );

        return $affected > 0;
    }    

    public function getStatusCount(string $status): int
    {
        if (!in_array($status, ['open', 'ignored', 'resolved'], true))
        {
            return 0;
        }

        return (int)$this->app->db()->fetchOne(
            'SELECT COUNT(*)
            FROM xf_brs_quote_integrity
            WHERE status = ?',
            $status
        );
    }

    public function getRecent(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $db = $this->app->db();
        $where = [];
        $params = [];

        if (!empty($filters['user_id']))
        {
            $where[] = 'q.user_id = ?';
            $params[] = (int)$filters['user_id'];
        }

        if (!empty($filters['domain']))
        {
            $where[] = 'q.added_domain = ?';
            $params[] = strtolower(trim((string)$filters['domain']));
        }

        if (!empty($filters['from']))
        {
            $where[] = 'q.detected_date >= ?';
            $params[] = (int)$filters['from'];
        }

        if (!empty($filters['to']))
        {
            $where[] = 'q.detected_date <= ?';
            $params[] = (int)$filters['to'];
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all')
        {
            $where[] = 'q.status = ?';
            $params[] = (string)$filters['status'];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = max(0, ($page - 1) * $perPage);

        $rows = $db->fetchAll("
            SELECT q.*
            FROM xf_brs_quote_integrity AS q
            {$whereSql}
            ORDER BY q.detected_date DESC, q.finding_id DESC
            LIMIT {$offset}, {$perPage}
        ", $params);

        $postIds = [];

        foreach ($rows as $row)
        {
            $postIds[] = (int)$row['post_id'];
            $postIds[] = (int)$row['quoted_post_id'];
        }

        $postIds = array_values(array_unique(array_filter($postIds)));

        $posts = [];

        if ($postIds)
        {
            $posts = $this->app->finder('XF:Post')
                ->whereIds($postIds)
                ->fetch()
                ->toArray();
        }

        $router = $this->app->router('public');

        foreach ($rows as &$row)
        {
            $post = $posts[$row['post_id']] ?? null;
            $quotedPost = $posts[$row['quoted_post_id']] ?? null;

            $row['Post'] = $post;
            $row['QuotedPost'] = $quotedPost;

            $row['post_url'] = $post
                ? $router->buildLink('canonical:posts', $post)
                : '';

            $row['quoted_post_url'] = $quotedPost
                ? $router->buildLink('canonical:posts', $quotedPost)
                : '';
        }

        unset($row);

        $total = (int)$db->fetchOne("
            SELECT COUNT(*)
            FROM xf_brs_quote_integrity AS q
            {$whereSql}
        ", $params);

        return [$rows, $total];
    }
}