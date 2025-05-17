<?php

namespace Olakunlevpn\JobSystem\Entity;


use Olakunlevpn\JobSystem\Helper\JobSystemHelper;
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


    public function getRewardCurrency()
    {
        if (JobSystemHelper::ensureDbCreditAddonInstalled() && is_numeric($this->currency_id)) {
            return \XF::em()->find('DBTech\Credits:Currency', $this->currency_id);
        }

        if ($this->currency_id == 'xfcoder_wallet_credit') {
            return [
                'title' => \XF::phrase('olakunlevpn_jobsystem_xfcoder_wallet_credit'),
                'column' => 'xfcoder_wallet_credit'
            ];
        }

        return [
            'title' => 'Unknown',
            'column' => null
        ];
    }



    /**
     * Get the number of spots remaining for this job
     *
     * @return int|string Returns the number of spots left or "Unlimited" if no limit is set
     */
    public function getRemainingSpots()
    {
        if ($this->max_completions <= 0) {
            return "Unlimited";
        }

        $approvedSubmissionsCount = \XF::finder('Olakunlevpn\JobSystem:Submission')
            ->where('job_id', $this->job_id)
            ->whereOr([
                ['status', '=', 'approved'],
                ['status', '=', 'pending']
            ])
            ->total();

        return max(0, $this->max_completions - $approvedSubmissionsCount);
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
            'reward_amount' => ['type' => self::FLOAT, 'default' => 0],
            'reward_currency' => ['type' => self::STR, 'default' => ''],
            'max_completions' => ['type' => self::UINT, 'default' => 0],
            'active' => ['type' => self::BOOL, 'default' => true],
            'type' => ['type' => self::STR, 'allowedValues' => ['text', 'url'], 'default' => 'text'],
            'has_attachment' => ['type' => self::BOOL, 'default' => false],
            'reward_type' => [
                'type' => self::STR,
                'required' => true,
                'allowedValues' => ['db_credits', 'trophy', 'xfcoder_wallet_credit'],
                'default' => 'trophy'
            ],
            'trophy_id' => ['type' => self::UINT, 'nullable' => true, 'default' => null],
            'created_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'updated_date' => ['type' => self::UINT, 'default' => \XF::$time],
        ];

        $structure->defaultWith = [];

        $structure->relations = [
            'Submissions' => [
                'entity' => 'Olakunlevpn\JobSystem:Submission',
                'type' => self::TO_MANY,
                'conditions' => 'job_id',
                'key' => 'job_id',
                'cascadeDelete' => true,
            ],
            'Application' => [
                'entity' => 'Olakunlevpn\JobSystem:Application',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['job_id', '=', '$job_id'],
                    ['user_id', '=', \XF::visitor()->user_id]
                ]
            ],

            'Trophy' => [
                'entity' => 'XF:Trophy',
                'type' => self::TO_ONE,
                'conditions' => 'trophy_id',
                'primary' => true
            ]
        ];

        if (JobSystemHelper::ensureDbCreditAddonInstalled()) {
            $structure->relations['RewardCurrency'] = [
                    'entity' => 'DBTech\Credits:Currency',
                    'type' => self::TO_ONE,
                    'conditions' => [['currency_id', '=', '$reward_currency']]
         ];
      }

        return $structure;
    }





}
