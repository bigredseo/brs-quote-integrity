<?php

namespace BRS\QuoteIntegrity\Job;

use XF\Job\AbstractJob;
use XF\Job\JobResult;

class HistoricalScan extends AbstractJob
{
    protected $defaultData = [
        'start_date' => 0,
        'end_date' => 0,
        'user_id' => 0,
        'node_id' => 0,
        'last_post_id' => 0,
        'scanned' => 0,
        'candidates' => 0,
        'findings' => 0
    ];

    public function run($maxRunTime): JobResult
    {
        $start = microtime(true);
        $analyzer = new \BRS\QuoteIntegrity\Service\QuoteAnalyzer($this->app);
        $repository = new \BRS\QuoteIntegrity\Repository\Finding($this->app);

        do
        {
            $finder = $this->app->finder('XF:Post')
                ->where('post_id', '>', (int)$this->data['last_post_id'])
                ->where('message', 'LIKE', '%[QUOTE%')
                ->order('post_id')
                ->limit(250);

            if (!empty($this->data['start_date']))
            {
                $finder->where('post_date', '>=', (int)$this->data['start_date']);
            }
            if (!empty($this->data['end_date']))
            {
                $finder->where('post_date', '<=', (int)$this->data['end_date']);
            }
            if (!empty($this->data['user_id']))
            {
                $finder->where('user_id', (int)$this->data['user_id']);
            }
            if (!empty($this->data['node_id']))
            {
                $finder->where('Thread.node_id', (int)$this->data['node_id']);
                $finder->with('Thread');
            }

            $posts = $finder->fetch();
            if (!$posts->count())
            {
                return $this->complete();
            }

            foreach ($posts as $post)
            {
                $this->data['last_post_id'] = (int)$post->post_id;
                $this->data['scanned']++;

                if (!$analyzer->couldContainRelevantQuote((string)$post->message))
                {
                    continue;
                }

                $this->data['candidates']++;
                $findings = $analyzer->analyzePost($post);

                $this->data['findings'] += $repository->syncPostFindings(
                    (int)$post->post_id,
                    $findings,
                    'historical'
                );

                if (microtime(true) - $start >= $maxRunTime)
                {
                    return $this->resume();
                }
            }
        }
        while (microtime(true) - $start < $maxRunTime);

        return $this->resume();
    }

    public function getStatusMessage(): string
    {
        return sprintf(
            'BRS Quote Integrity: %d posts scanned, %d candidates, %d findings recorded',
            (int)$this->data['scanned'],
            (int)$this->data['candidates'],
            (int)$this->data['findings']
        );
    }

    public function canCancel(): bool
    {
        return true;
    }

    public function canTriggerByChoice(): bool
    {
        return true;
    }
}
