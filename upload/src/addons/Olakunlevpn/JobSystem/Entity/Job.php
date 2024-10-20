<?php

namespace Olakunlevpn\JobSystem\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $job_id
 * @property string $title
 * @property string $description
 * @property string $details
 * @property int $reward_amount
 * @property string $reward_currency
 * @property int $max_completions
 * @property bool $active
 * @property int $created_date
 * @property int $updated_date
 *
 * RELATIONS
 * @property-read \XF\Mvc\Entity\AbstractCollection<\Olakunlevpn\JobSystem\Entity\Submission> $Submissions
 */
class Job extends Entity
{

    protected function _preSave(): void
    {
        if($this->isUpdate()){
            $this->updated_date = \XF::$time;
        }
    }


    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_job_system_jobs';  // The table name
        $structure->shortName = 'Olakunlevpn\JobSystem:Job';
        $structure->contentType = "olakunlevpn_job_system";
        $structure->primaryKey = 'job_id'; // The primary key column

        // Fields (columns) definitions
        $structure->columns = [
            'job_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'title' => ['type' => self::STR, 'required' => true, 'censor' => true],
            'description' => ['type' => self::STR, 'required' => true , 'censor' => true],
            'details' => ['type' => self::STR, 'required' => true, 'censor' => true],
            'reward_amount' => ['type' => self::UINT, 'default' => 0],  // Add this
            'reward_currency' => ['type' => self::STR, 'default' => ''],  // Add this
            'max_completions' => ['type' => self::UINT, 'default' => 0],
            'active' => ['type' => self::BOOL, 'default' => true],
            'type' => ['type' => self::STR, 'allowedValues' => ['text', 'url'], 'default' => 'text'],
            'has_attachment' => ['type' => self::BOOL, 'default' => false],
            'created_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'updated_date' => ['type' => self::UINT, 'default' => \XF::$time],
        ];

        // Default values when creating a new job
        $structure->defaultWith = [];

        // Relations (if necessary)
        $structure->relations = [
            'Submissions' => [
                'entity' => 'Olakunlevpn\JobSystem:Submission',
                'type' => self::TO_MANY,
                'conditions' => 'job_id',
                'key' => 'job_id'
            ],
            'RewardCurrency' => [
                'entity' => 'DBTech\Credits:Currency',
                'type' => self::TO_ONE,
                'conditions' => [['currency_id', '=', '$reward_currency']]
            ]
        ];

        return $structure;
    }





}
