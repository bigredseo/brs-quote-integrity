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
                'source' => $source === 'historical' ? 'historical' : 'live'
            ]);
            return true;
        }
        catch (\XF\Db\DuplicateKeyException $e)
        {
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

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = max(0, ($page - 1) * $perPage);

        $rows = $db->fetchAll("\n            SELECT q.*\n            FROM xf_brs_quote_integrity AS q\n            {$whereSql}\n            ORDER BY q.detected_date DESC, q.finding_id DESC\n            LIMIT {$offset}, {$perPage}\n        ", $params);

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

        $total = (int)$db->fetchOne("\n            SELECT COUNT(*)\n            FROM xf_brs_quote_integrity AS q\n            {$whereSql}\n        ", $params);

        return [$rows, $total];
    }
}
