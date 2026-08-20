<?php

namespace BRS\QuoteIntegrity\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class QuoteIntegrity extends AbstractController
{
    public function actionIndex(ParameterBag $params)
    {
        $page = $this->filterPage();
        $username = trim((string)$this->filter('username', 'str'));
        $domain = trim((string)$this->filter('domain', 'str'));
        $fromInput = trim((string)$this->filter('from', 'str'));
        $toInput = trim((string)$this->filter('to', 'str'));
        $status = trim((string)$this->filter('status', 'str'));

        if (!in_array($status, ['open', 'ignored', 'resolved', 'all'], true))
        {
            $status = 'open';
        }

        $userId = 0;

        if ($username !== '')
        {
            $user = $this->finder('XF:User')
                ->where('username', $username)
                ->fetchOne();

            $userId = $user ? (int)$user->user_id : -1;
        }

        $from = $fromInput !== ''
            ? strtotime($fromInput . ' 00:00:00')
            : 0;

        $to = $toInput !== ''
            ? strtotime($toInput . ' 23:59:59')
            : 0;

        $repository = new \BRS\QuoteIntegrity\Repository\Finding($this->app());

        [$findings, $total] = $repository->getRecent([
            'user_id' => $userId,
            'domain' => $domain,
            'from' => $from,
            'to' => $to,
            'status' => $status
        ], $page, 50);

        $openCount = $repository->getStatusCount('open');
        $ignoredCount = $repository->getStatusCount('ignored');

        $viewParams = [
            'findings' => $findings,
            'total' => $total,
            'openCount' => $openCount,
            'ignoredCount' => $ignoredCount,
            'page' => $page,
            'perPage' => 50,
            'filters' => [
                'username' => $username,
                'domain' => $domain,
                'from' => $fromInput,
                'to' => $toInput,
                'status' => $status
            ]
        ];

        return $this->view(
            'BRS\\QuoteIntegrity:Index',
            'brs_quote_integrity_index',
            $viewParams
        );
    }

    public function actionIgnore(ParameterBag $params)
    {
        $this->assertPostOnly();

        $findingId = (int)$this->filter('finding_id', 'uint');

        if (!$findingId)
        {
            return $this->error('No finding was specified.');
        }

        $repository = new \BRS\QuoteIntegrity\Repository\Finding($this->app());

        $ignored = $repository->ignore(
            $findingId,
            (int)\XF::visitor()->user_id
        );

        if (!$ignored)
        {
            return $this->error(
                'This finding could not be ignored. It may no longer be open.'
            );
        }

        return $this->redirect(
            $this->buildLink('brs-quote-integrity'),
            'The finding has been ignored.'
        );
    }

    public function actionUnignore(ParameterBag $params)
    {
        $this->assertPostOnly();

        $findingId = (int)$this->filter('finding_id', 'uint');

        if (!$findingId)
        {
            return $this->error('No finding was specified.');
        }

        $repository = new \BRS\QuoteIntegrity\Repository\Finding($this->app());

        $restored = $repository->unignore($findingId);

        if (!$restored)
        {
            return $this->error(
                'This finding could not be restored. It may no longer be ignored.'
            );
        }

        return $this->redirect(
            $this->buildLink('brs-quote-integrity', null, ['status' => 'ignored']),
            'The finding has been restored to open findings.'
        );
    }    
    public function actionScan()
    {
        if ($this->isPost())
        {
            $input = $this->filter([
                'username' => 'str',
                'start_date' => 'str',
                'end_date' => 'str',
                'node_id' => 'uint'
            ]);

            $userId = 0;

            if (trim($input['username']) !== '')
            {
                $user = $this->finder('XF:User')
                    ->where('username', trim($input['username']))
                    ->fetchOne();

                if (!$user)
                {
                    return $this->error(
                        'The requested user could not be found.'
                    );
                }

                $userId = (int)$user->user_id;
            }

            $startDate = trim($input['start_date']) !== ''
                ? strtotime($input['start_date'] . ' 00:00:00')
                : 0;

            $endDate = trim($input['end_date']) !== ''
                ? strtotime($input['end_date'] . ' 23:59:59')
                : 0;

            $jobData = [
                'start_date' => (int)$startDate,
                'end_date' => (int)$endDate,
                'user_id' => $userId,
                'node_id' => (int)$input['node_id']
            ];

            $this->app()->jobManager()->enqueueUnique(
                'brsQuoteIntegrityHistorical',
                'BRS\\QuoteIntegrity:HistoricalScan',
                $jobData,
                false
            );

            return $this->message(
                'The BRS Quote Integrity historical scan has been queued. It runs in batches and only records matching findings.'
            );
        }

        return $this->view(
            'BRS\\QuoteIntegrity:Scan',
            'brs_quote_integrity_scan'
        );
    }
}