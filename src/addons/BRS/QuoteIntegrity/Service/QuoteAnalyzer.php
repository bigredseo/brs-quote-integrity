<?php

namespace BRS\QuoteIntegrity\Service;

use XF\Entity\Post;

class QuoteAnalyzer
{
    protected $app;

    public function __construct(\XF\App $app)
    {
        $this->app = $app;
    }

    public function analyzePost(Post $post): array
    {
        $message = (string)$post->message;
        if (!$this->couldContainRelevantQuote($message))
        {
            return [];
        }

        $quotes = $this->extractAttributedQuotes($message);
        if (!$quotes)
        {
            return [];
        }

        $postIds = array_values(array_unique(array_column($quotes, 'post_id')));
        $sourcePosts = $this->app->finder('XF:Post')
            ->whereIds($postIds)
            ->fetch()
            ->toArray();

        $findings = [];

        foreach ($quotes as $quote)
        {
            $sourcePost = $sourcePosts[$quote['post_id']] ?? null;
            if (!$sourcePost)
            {
                continue;
            }

            $quotedUrls = $this->extractUrls($quote['content']);
            if (!$quotedUrls)
            {
                continue;
            }

            $sourceUrls = $this->extractUrls((string)$sourcePost->message);
            $sourceKeys = [];
            foreach ($sourceUrls as $url)
            {
                $sourceKeys[$this->normalizeUrl($url)] = true;
            }

            foreach ($quotedUrls as $url)
            {
                $normalized = $this->normalizeUrl($url);
                if ($normalized === '' || isset($sourceKeys[$normalized]))
                {
                    continue;
                }

                $findings[] = [
                    'post_id' => (int)$post->post_id,
                    'thread_id' => (int)$post->thread_id,
                    'user_id' => (int)$post->user_id,
                    'username' => (string)$post->username,
                    'quoted_post_id' => (int)$sourcePost->post_id,
                    'quoted_user_id' => (int)$sourcePost->user_id,
                    'quoted_username' => (string)$sourcePost->username,
                    'quoted_post_url' => $this->app
                        ->router('public')
                        ->buildLink('canonical:posts', $sourcePost),
                    'added_url' => $url,
                    'added_domain' => $this->domainFromUrl($url),
                    'normalized_url' => $normalized
                ];
            }
        }

        return $this->deduplicateFindings($findings);
    }

    public function couldContainRelevantQuote(string $message): bool
    {
        return stripos($message, '[QUOTE') !== false
            && (stripos($message, 'http://') !== false
                || stripos($message, 'https://') !== false
                || stripos($message, '[URL') !== false);
    }

    protected function extractAttributedQuotes(string $message): array
    {
        preg_match_all('/\[QUOTE(?:=([^\]]+))?\]|\[\/QUOTE\]/i', $message, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        $stack = [];
        $quotes = [];

        foreach ($matches as $match)
        {
            $token = $match[0][0];
            $offset = $match[0][1];

            if (stripos($token, '[/QUOTE]') === 0)
            {
                if (!$stack)
                {
                    continue;
                }

                $open = array_pop($stack);
                $content = substr($message, $open['content_start'], $offset - $open['content_start']);

                if ($open['post_id'])
                {
                    $quotes[] = [
                        'post_id' => $open['post_id'],
                        'member_id' => $open['member_id'],
                        'content' => $content
                    ];
                }
                continue;
            }

            $option = $match[1][0] ?? '';
            $option = trim($option, " \t\n\r\0\x0B\"'");
            $postId = 0;
            $memberId = 0;

            if (preg_match('/(?:^|,)\s*post\s*:\s*(\d+)/i', $option, $m))
            {
                $postId = (int)$m[1];
            }
            if (preg_match('/(?:^|,)\s*member\s*:\s*(\d+)/i', $option, $m))
            {
                $memberId = (int)$m[1];
            }

            $stack[] = [
                'post_id' => $postId,
                'member_id' => $memberId,
                'content_start' => $offset + strlen($token)
            ];
        }

        return $quotes;
    }

    protected function extractUrls(string $text): array
    {
        $urls = [];

        preg_match_all('/\[URL(?:=(["\']?)(https?:\/\/[^\]\"\']+)\1)?\](.*?)\[\/URL\]/is', $text, $bbMatches, PREG_SET_ORDER);
        foreach ($bbMatches as $match)
        {
            if (!empty($match[2]))
            {
                $urls[] = $match[2];
            }
            elseif (preg_match('/https?:\/\/[^\s\[]+/i', strip_tags($match[3]), $inner))
            {
                $urls[] = $inner[0];
            }
        }

        $withoutUrlBbCode = preg_replace('/\[URL(?:=[^\]]+)?\].*?\[\/URL\]/is', ' ', $text);
        preg_match_all('/https?:\/\/[^\s\[\]<>"\']+/i', (string)$withoutUrlBbCode, $bareMatches);
        foreach ($bareMatches[0] as $url)
        {
            $urls[] = $url;
        }

        $clean = [];
        foreach ($urls as $url)
        {
            $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $url = rtrim($url, ".,;:!?)]}\"'");
            if ($url !== '')
            {
                $clean[$url] = $url;
            }
        }

        return array_values($clean);
    }

    protected function normalizeUrl(string $url): string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $parts = @parse_url($url);
        if (!$parts || empty($parts['host']))
        {
            return strtolower(rtrim($url, '/'));
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '/';
        $path = $path === '' ? '/' : $path;

        $query = [];
        if (!empty($parts['query']))
        {
            parse_str($parts['query'], $query);
            foreach (array_keys($query) as $key)
            {
                if (preg_match('/^(utm_|fbclid$|gclid$|dclid$|msclkid$)/i', (string)$key))
                {
                    unset($query[$key]);
                }
            }
            ksort($query);
        }

        $normalized = $scheme . '://' . $host . $port . $path;
        if ($query)
        {
            $normalized .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return rtrim($normalized, '/');
    }

    protected function domainFromUrl(string $url): string
    {
        $host = (string)(parse_url($url, PHP_URL_HOST) ?: '');
        return strtolower(preg_replace('/^www\./i', '', $host));
    }

    protected function deduplicateFindings(array $findings): array
    {
        $unique = [];
        foreach ($findings as $finding)
        {
            $key = $finding['quoted_post_id'] . '|' . $finding['normalized_url'];
            $unique[$key] = $finding;
        }
        return array_values($unique);
    }
}
