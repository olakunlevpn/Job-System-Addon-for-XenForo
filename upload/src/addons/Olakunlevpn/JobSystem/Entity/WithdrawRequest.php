<?php

namespace Olakunlevpn\JobSystem\Entity;

use Olakunlevpn\JobSystem\Helper\JobSystemHelper;
use XF;
use XF\Entity\ApprovalQueue;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int           withdraw_request_id
 * @property int           user_id
 * @property string        currency_id
 * @property string        payment_profile
 * @property string        payment_profile_data
 * @property float         amount
 * @property string        status
 * @property int           creation_date
 * @property int           change_date
 *
 * RELATIONS
 * @property User          User
 * @property ApprovalQueue ApprovalQueue
 */
class WithdrawRequest extends Entity
{
    /**
     * @return string
     */
    public function getStatusPhrase()
    {
        return match ($this->status) {
            'created' => 'Created',
            'completed' => 'Completed',
            default => 'Rejected',
        };
    }


    /**
     * @return bool
     */
    public function canView()
    {
        return XF::visitor()->is_super_admin || XF::visitor()->user_id == $this->user_id;
    }

    /**
     *
     */
    protected function _preSave()
    {
        if ($this->isUpdate()) {
            $this->change_date = XF::$time;
        }
    }

    public function getCurrency()
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
     *
     */
    protected function _postSave()
    {
        $approvalChange = $this->isStateChanged('status', 'created');
        $deletionChange = $this->isStateChanged('status', 'rejected');

        if ($approvalChange === 'enter' && $this->isInsert()) {
            $currencyColumn = $this->Currency;
            \XF::logError($currencyColumn['column']);

            $user = $this->User;
            $user->set($currencyColumn['column'], $user->get($currencyColumn['column']) - $this->amount);
            $user->save(false);

            if ($user->hasErrors()) {
                $this->status = 'rejected';
                $this->save();
            }

            $approvalQueue = $this->getRelationOrDefault('ApprovalQueue', false);
            $approvalQueue->content_date = $this->creation_date;
            $approvalQueue->save();
        }

        if ($this->isUpdate()) {
            if ($approvalChange === 'leave' && $this->ApprovalQueue) {
                $this->ApprovalQueue->delete();
            }

            if ($deletionChange === 'enter') {
                $currencyColumn = $this->Currency;

                $user = $this->User;
                $user->set($currencyColumn['column'], $user->get($currencyColumn['column']) + $this->amount);
                $user->save(false);
            }
        }
    }

    /**
     * @param  Structure  $structure
     *
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_job_system_withdraw_request';
        $structure->shortName = 'Olakunlevpn\JobSystem:WithdrawRequest';
        $structure->primaryKey = 'withdraw_request_id';
        $structure->columns = [
            'withdraw_request_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'currency_id' => ['type' => self::STR, 'required' => true],
            'payment_profile' => ['type' => self::STR, 'required' => true],
            'payment_profile_data' => ['type' => self::STR, 'required' => true],
            'amount' => ['type' => self::UINT, 'required' => true, 'min' => 1],
            'status' => [
                'type' => self::STR, 'required' => true, 'default' => 'created',
                'allowedValues' => ['created', 'rejected', 'completed']
            ],
            'creation_date' => ['type' => self::UINT, 'required' => true, 'default' => XF::$time],
            'change_date' => ['type' => self::UINT, 'required' => true, 'default' => 0]
        ];
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],

            'ApprovalQueue' => [
                'entity' => 'XF:ApprovalQueue',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['content_type', '=', 'job_submission_withdraw'],
                    ['content_id', '=', '$withdraw_request_id']
                ],
                'primary' => true
            ]
        ];

        if (JobSystemHelper::ensureDbCreditAddonInstalled()) {
            $structure->relations['Currency'] = [
                'entity' => 'DBTech\Credits:Currency',
                'type' => self::TO_ONE,
                'conditions' => 'currency_id',
                'primary' => true
            ];
        }

        $structure->getters = [
            'status_phrase' => false,
            'Currency' => true
        ];

        return $structure;
    }

}