<?php

namespace BRS\QuoteIntegrity\XF\Entity;

use XF\Mvc\Entity\Structure;

class Post extends XFCP_Post
{
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->getters['BRSQuoteIntegrityFindings'] = false;

        return $structure;
    }

    public function getBRSQuoteIntegrityFindings(): array
    {
        $message = (string) $this->message;
        $analyzer = new \BRS\QuoteIntegrity\Service\QuoteAnalyzer(\XF::app());

        if (!$analyzer->couldContainRelevantQuote($message))
        {
            return [];
        }

        try
        {
            return $analyzer->analyzePost($this);
        }
        catch (\Throwable $e)
        {
            \XF::logException($e, false, 'BRS Quote Integrity display check: ');

            return [];
        }
    }
}
