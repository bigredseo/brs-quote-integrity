<?php

namespace BRS\QuoteIntegrity;

use XF\Mvc\Entity\Entity;

class Listener
{
    public static function postEntityPostSave(Entity $entity): void
    {
        if (!$entity instanceof \XF\Entity\Post)
        {
            return;
        }

        if (!$entity->isInsert() && !$entity->isChanged('message'))
        {
            return;
        }

        try
        {
            $analyzer = new Service\QuoteAnalyzer(\XF::app());
            $findings = $analyzer->analyzePost($entity);
            if (!$findings)
            {
                return;
            }

            $repository = new Repository\Finding(\XF::app());
            $repository->recordMany($findings, 'live');
        }
        catch (\Throwable $e)
        {
            \XF::logException($e, false, 'BRS Quote Integrity live check: ');
        }
    }
}
