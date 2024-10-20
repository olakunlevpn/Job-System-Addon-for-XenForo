<?php

namespace Olakunlevpn\JobSystem;

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




    public function installStep1(): void
    {




         $this->applyTables();



        $this->applyGlobalPermission('attachment', 'upload', 'job_system_submissions');
        $this->db()->insert('xf_content_type_field', [
            'content_type' => 'job_system_submissions',
            'field_name' => 'attachment_handler_class',
            'field_value' => 'Olakunlevpn\JobSystem\Attachment\Submission'
        ]);


    }



    public function uninstallStep1()
    {
        $sm = $this->schemaManager();

        // Drop your custom tables
        $sm->dropTable('xf_job_system_jobs');
        $sm->dropTable('xf_job_system_submissions');
    }


    public function uninstallStep2()
    {
        $contentTypes = ['job_system_submissions'];

        // Uninstall content type data
        $this->uninstallContentTypeData($contentTypes);
    }

    public function uninstallStep3()
    {
        $contentType = 'job_system_submissions';

        $this->db()->delete('xf_content_type_field', 'content_type = ?', $contentType);

    }



    public function uninstallStep4(): void
    {
        $sm = $this->schemaManager();

        // Drop the tables created for the addon
        foreach (array_keys($this->getTables()) as $tableName) {
            $sm->dropTable($tableName);
        }

        // Remove content type fields related to job_submission_tasks
        $this->db()->delete('xf_content_type_field', 'content_type = ?', 'job_submission_tasks');


        // Remove attachment and alert handler configurations
        $this->db()->delete('xf_content_type_field', 'content_type = ? AND field_name = ?', ['job_submission_tasks', 'attachment_handler_class']);
        $this->db()->delete('xf_content_type_field', 'content_type = ? AND field_name = ?', ['job_submission_tasks', 'alert_handler_class']);
        $this->db()->delete('xf_content_type_field', 'content_type = ? AND field_name = ?', ['job_submission_tasks', 'entity']);



        // Remove global permissions (if any were added)
        $this->db()->delete('xf_permission_entry', 'permission_group_id = ?', 'job_system_submissions');
        $this->db()->delete('xf_permission_entry_content', 'permission_group_id = ?', 'job_system_submissions');

        $this->schemaManager()->dropTable('xf_job_system_withdraw_request');

    }

    protected function applyTables(): void
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $closure) {
            $sm->createTable($tableName, $closure);  // Creates the table
            $sm->alterTable($tableName, $closure);   // Alters the table if needed
        }
    }



    protected function getTables(): array
    {
        $tables = [];

        // Define the 'xf_job_system_jobs' table
        $tables['xf_job_system_jobs'] = function ($table) {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table, 'job_id', 'int')->autoIncrement();
            $this->addOrChangeColumn($table, 'title', 'varchar', 255);
            $this->addOrChangeColumn($table, 'description', 'text');
            $this->addOrChangeColumn($table, 'details', 'mediumtext');
            $this->addOrChangeColumn($table, 'reward_amount', 'int');
            $this->addOrChangeColumn($table, 'reward_currency', 'varchar', 50);
            $this->addOrChangeColumn($table, 'max_completions', 'int')->setDefault(0);
            $this->addOrChangeColumn($table, 'active', 'bool')->setDefault(1);
            $this->addOrChangeColumn($table, 'type', 'enum')->values(['text', 'url'])->setDefault('text');
            $this->addOrChangeColumn($table, 'has_attachment', 'bool')->setDefault(0);
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




        return $tables;
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



}