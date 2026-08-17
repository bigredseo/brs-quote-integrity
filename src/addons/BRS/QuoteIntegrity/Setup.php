<?php

namespace BRS\QuoteIntegrity;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1()
    {
        $this->schemaManager()->createTable('xf_brs_quote_integrity', function (Create $table)
        {
            $table->addColumn('finding_id', 'int')->autoIncrement();
            $table->addColumn('post_id', 'int');
            $table->addColumn('thread_id', 'int')->setDefault(0);
            $table->addColumn('user_id', 'int')->setDefault(0);
            $table->addColumn('username', 'varchar', 50)->setDefault('');
            $table->addColumn('quoted_post_id', 'int');
            $table->addColumn('quoted_user_id', 'int')->setDefault(0);
            $table->addColumn('quoted_username', 'varchar', 50)->setDefault('');
            $table->addColumn('detected_date', 'int');
            $table->addColumn('added_url', 'text');
            $table->addColumn('added_domain', 'varchar', 255)->setDefault('');
            $table->addColumn('url_hash', 'varbinary', 32);
            $table->addColumn('source', 'enum')->values(['live', 'historical'])->setDefault('live');
            $table->addPrimaryKey('finding_id');
            $table->addUniqueKey(['post_id', 'quoted_post_id', 'url_hash'], 'post_quote_url');
            $table->addKey('detected_date');
            $table->addKey('user_id');
            $table->addKey('quoted_post_id');
            $table->addKey('added_domain');
        });
    }

    public function uninstallStep1()
    {
        $this->schemaManager()->dropTable('xf_brs_quote_integrity');
    }
}
