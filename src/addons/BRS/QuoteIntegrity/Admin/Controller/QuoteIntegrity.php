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

        $userId = 0;
        if ($username !== '')
        {
            $user = $this->finder('XF:User')->where('username', $username)->fetchOne();
            $userId = $user ? (int)$user->user_id : -1;
        }

        $from = $fromInput !== '' ? strtotime($fromInput . ' 00:00:00') : 0;
        $to = $toInput !== '' ? strtotime($toInput . ' 23:59:59') : 0;

        $repository = new \BRS\QuoteIntegrity\Repository\Finding($this->app());
        [$findings, $total] = $repository->getRecent([
            'user_id' => $userId,
            'domain' => $domain,
            'from' => $from,
            'to' => $to
        ], $page, 50);

        $viewParams = [
            'findings' => $findings,
            'total' => $total,
            'page' => $page,
            'perPage' => 50,
            'filters' => [
                'username' => $username,
                'domain' => $domain,
                'from' => $fromInput,
                'to' => $toInput
            ]
        ];

        return $this->view('BRS\\QuoteIntegrity:Index', 'brs_quote_integrity_index', $viewParams);
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
                $user = $this->finder('XF:User')->where('username', trim($input['username']))->fetchOne();
                if (!$user)
                {
                    return $this->error('The requested user could not be found.');
                }
                $userId = (int)$user->user_id;
            }

            $startDate = trim($input['start_date']) !== '' ? strtotime($input['start_date'] . ' 00:00:00') : 0;
            $endDate = trim($input['end_date']) !== '' ? strtotime($input['end_date'] . ' 23:59:59') : 0;

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

            return $this->message('The BRS Quote Integrity historical scan has been queued. It runs in batches and only records matching findings.');
        }

        return $this->view('BRS\\QuoteIntegrity:Scan', 'brs_quote_integrity_scan');
    }
}
