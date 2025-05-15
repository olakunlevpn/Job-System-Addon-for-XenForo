<?php

namespace Olakunlevpn\JobSystem;

use DBTech\Credits\Repository\CurrencyRepository;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\AbstractDdl;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Column;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;


    /**
     * @return void
     */
    public function installStep1(): void
    {


       $this->applyTables();

        $this->applyGlobalPermission('attachment', 'upload', 'job_system_submissions');

        $this->rebuildDragonByteCreditsCaches();




    }


    /**
     * @return void
     */
    public function upgrade1020001Step1()
    {
        $this->schemaManager()->alterTable('xf_job_system_jobs', function(Alter $table)
        {
            $table->changeColumn('reward_amount', 'decimal', '65,2')->unsigned(false)->setDefault(0);
            $table->addColumn('reward_type', 'enum', ['db_credits', 'trophy'])
                ->nullable(false)
                ->setDefault('db_credits');
            $table->addColumn('trophy_id', 'int')->nullable();
        });

        $sm = $this->schemaManager();

        $sm->createTable('xf_job_system_apply_applications', function (\XF\Db\Schema\Create $table) {
            $table->addColumn('application_id', 'int')->autoIncrement();
            $table->addColumn('job_id', 'int');
            $table->addColumn('user_id', 'int');
            $table->addColumn('status', 'enum')->values(['pending', 'approved', 'rejected'])->setDefault('pending');
            $table->addColumn('created_date', 'int')->setDefault(0);
            $table->addColumn('approved_date', 'int')->nullable();
            $table->addColumn('admin_comment', 'text')->nullable();

            $table->addPrimaryKey('application_id');
            $table->addKey(['job_id', 'user_id'], 'job_user_index');

        });


        $this->rebuildDragonByteCreditsCaches();



    }


    public function upgrade2000001Step1()
    {

        $sm = $this->schemaManager();

        if ($sm->tableExists('xf_job_system_applications')) {
            $sm->renameTable('xf_job_system_applications', 'xf_job_system_apply_applications');
        }

        $this->rebuildDragonByteCreditsCaches();

    }

    public function upgrade2000002Step1()
    {

        $this->schemaManager()->alterTable('xf_job_system_submissions', function(Alter $table)
        {
            $table->addColumn('attach_count', 'int')->setDefault(0);
        });
        $this->rebuildDragonByteCreditsCaches();

    }



    public function upgrade3000000Step1()
    {
        $sm = $this->schemaManager();

        // Update the reward_type column to include 'xfcoder_wallet_credit'
        $sm->alterTable('xf_job_system_jobs', function (Alter $table) {
            $table->changeColumn('reward_type', 'enum', ['db_credits', 'trophy', 'xfcoder_wallet_credit'])
                ->nullable(false)
                ->setDefault('trophy');
        });
    }



    /**
     * @return void
     */
    public function uninstallStep1()
    {
        $sm = $this->schemaManager();

        // Drop your custom tables
        $sm->dropTable('xf_job_system_jobs');
        $sm->dropTable('xf_job_system_submissions');
        $sm->dropTable('xf_job_system_apply_applications');
        $sm->dropTable('xf_job_system_withdraw_request');
    }


    /**
     * @return void
     */
    public function uninstallStep2()
    {
        $sm = $this->schemaManager();
        foreach (array_keys($this->getTables()) as $tableName) {
            $sm->dropTable($tableName);
        }
        $this->uninstallContentTypeDataForAddon();
        $this->rebuildDragonByteCreditsCaches();
    }



    /**
     * @return void
     */
    public function uninstallStep3(): void
    {

        $db = $this->db();
        $db->beginTransaction();

        $this->db()->delete('xf_permission_entry', 'permission_group_id = ?', 'job_system_submissions');
        $this->db()->delete('xf_permission_entry_content', 'permission_group_id = ?', 'job_system_submissions');

        $db->commit();
    }


    /**
     * @return void
     */
    protected function applyTables(): void
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $closure) {
            $sm->createTable($tableName, $closure);
            $sm->alterTable($tableName, $closure);
        }
    }


    /**
     * @return array
     */
    protected function getTables(): array
    {
        $tables = [];

        $tables['xf_job_system_jobs'] = function ($table) {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table, 'job_id', 'int')->autoIncrement();
            $this->addOrChangeColumn($table, 'title', 'varchar', 255);
            $this->addOrChangeColumn($table, 'description', 'text');
            $this->addOrChangeColumn($table, 'details', 'mediumtext');
            $this->addOrChangeColumn($table, 'reward_amount', 'decimal', '65,2')->unsigned(false)->setDefault(0);
            $this->addOrChangeColumn($table, 'reward_currency', 'varchar', 50);
            $this->addOrChangeColumn($table, 'max_completions', 'int')->setDefault(0);
            $this->addOrChangeColumn($table, 'active', 'bool')->setDefault(1);
            $this->addOrChangeColumn($table, 'type', 'enum')->values(['text', 'url'])->setDefault('text');
            $this->addOrChangeColumn($table, 'has_attachment', 'bool')->setDefault(0);
            $this->addOrChangeColumn($table,'reward_type', 'enum')->values(['db_credits', 'trophy'])->setDefault('db_credits');
            $this->addOrChangeColumn($table,'trophy_id', 'int')->nullable();
            $this->addOrChangeColumn($table, 'created_date', 'int')->setDefault(0);
            $this->addOrChangeColumn($table, 'updated_date', 'int')->setDefault(0);
            $table->addPrimaryKey('job_id');
        };


        $tables['xf_job_system_submissions'] = function ($table) {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table, 'submission_id', 'int')->autoIncrement();
            $this->addOrChangeColumn($table, 'job_id', 'int');
            $this->addOrChangeColumn($table, 'user_id', 'int');
            $this->addOrChangeColumn($table, 'submission_data', 'text');
            $this->addOrChangeColumn($table, 'status', 'enum')->values(['pending', 'approved', 'rejected'])->setDefault('pending');
            $this->addOrChangeColumn($table, 'admin_comment', 'text')->nullable();
            $this->addOrChangeColumn($table, 'submitted_date', 'int')->setDefault(0);
            $this->addOrChangeColumn($table, 'reviewed_date', 'int')->setDefault(0);
            $this->addOrChangeColumn($table,'attach_count', 'int')->setDefault(0);
            $this->addOrChangeColumn($table, 'attachment_hash', 'varchar', '255')->nullable();
            $table->addPrimaryKey('submission_id');
        };


        $tables['xf_job_system_withdraw_request'] = function ($table) {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table, 'withdraw_request_id', 'int')->autoIncrement();
            $this->addOrChangeColumn($table, 'user_id', 'int');
            $this->addOrChangeColumn($table, 'currency_id', 'int');
            $this->addOrChangeColumn($table, 'payment_profile', 'text');
            $this->addOrChangeColumn($table, 'payment_profile_data', 'text');
            $this->addOrChangeColumn($table, 'amount', 'float');
            $this->addOrChangeColumn($table, 'status', 'enum')->values(['created', 'rejected', 'completed']);
            $this->addOrChangeColumn($table, 'creation_date', 'int')->setDefault(0);
            $this->addOrChangeColumn($table, 'change_date', 'int')->setDefault(0);
            $table->addPrimaryKey('withdraw_request_id');
        };

        $tables['xf_job_system_apply_applications'] = function ($table) {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table,'application_id', 'int')->autoIncrement();
            $this->addOrChangeColumn($table,'job_id', 'int');
            $this->addOrChangeColumn($table,'user_id', 'int');
            $this->addOrChangeColumn($table,'status', 'enum')->values(['pending', 'approved', 'rejected'])->setDefault('pending');
            $this->addOrChangeColumn($table,'created_date', 'int')->setDefault(0);
            $this->addOrChangeColumn($table,'approved_date', 'int')->nullable();
            $this->addOrChangeColumn($table,'admin_comment', 'text')->nullable();

            $table->addPrimaryKey('application_id');
            $table->addKey(['job_id', 'user_id'], 'job_user_index');
        };




        return $tables;
    }


    /**
     * Retrieves the content types used by the Job System addon.
     *
     * @return string[]
     */
    protected function getContentTypes(): array
    {
        return [
            'job_application',
            'job_submission_tasks',
            'job_submission_withdraw',
            'job_system_submissions'
        ];
    }


    protected function uninstallContentTypeDataForAddon(): void
    {
        $contentTypes = $this->getContentTypes();
        if ($contentTypes) {
            $this->uninstallContentTypeData($contentTypes);
        }
    }



    /**
     * @param AbstractDdl $table
     * @param string $name
     * @param string|null $type
     * @param int|string|string[]|null $length
     *
     * @return Column
     */
    protected function addOrChangeColumn(
        AbstractDdl $table,
        string $name,
        ?string $type = null,
        array|int|string|null $length = null
    ): Column
    {
        if ($table instanceof Create)
        {
            $table->checkExists(true);

            return $table->addColumn($name, $type, $length);
        }
        else if ($table instanceof Alter)
        {
            if ($table->getColumnDefinition($name))
            {
                return $table->changeColumn($name, $type, $length);
            }

            return $table->addColumn($name, $type, $length);
        }
        else
        {
            throw new \LogicException('Unknown schema DDL type ' . \get_class($table));
        }
    }


    /**
     * @return void
     */
    protected function rebuildDragonByteCreditsCaches()
    {
        $sm = $this->schemaManager();
        if ($sm->tableExists('xf_dbtech_credits_event')) {
            /** @var CurrencyRepository::class $eventRepo */
            $currencyRepo = \XF::repository(CurrencyRepository::class);
            $currencyRepo->rebuildCache();
        }
    }





}