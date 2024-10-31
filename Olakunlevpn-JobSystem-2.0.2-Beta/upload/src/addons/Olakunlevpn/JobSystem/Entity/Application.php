<?php

namespace Olakunlevpn\JobSystem\Entity;

use XF;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Application extends Entity
{
    /**
     * @return bool
     */
    public function canView()
    {
        return XF::visitor()->is_super_admin || XF::visitor()->user_id == $this->user_id;
    }


    protected function _preSave()
    {
        if ($this->isUpdate())
        {
            $this->approved_date = XF::$time;
        }
    }




    protected function _postSave()
    {
        $approvalChange = $this->isStateChanged('status', 'pending');
        $rejectionChange = $this->isStateChanged('status', 'rejected');

        if ($approvalChange === 'enter' && $this->isInsert())
        {
            $approvalQueue = $this->getRelationOrDefault('ApprovalQueue', false);
            $approvalQueue->content_date  = $this->created_date;
            $approvalQueue->save();
        }

        // On updating status, remove from approval queue if approved
        if ($this->isUpdate())
        {
            if ($approvalChange === 'leave' && $this->ApprovalQueue)
            {
                $this->ApprovalQueue->delete();
            }

            // Handle rejection changes
            if ($rejectionChange === 'enter')
            {

            }
        }
    }



    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_job_system_apply_applications';
        $structure->shortName = 'Olakunlevpn\JobSystem:Application';
        $structure->primaryKey = 'application_id';

        $structure->columns = [
            'application_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'job_id' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'status' => ['type' => self::STR, 'allowedValues' => ['pending', 'approved', 'rejected'], 'default' => 'pending'],
            'created_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'approved_date' => ['type' => self::UINT, 'default' => 0],
            'admin_comment' => ['type' => self::STR, 'nullable' => true],
        ];

        $structure->getters = [];

        $structure->relations = [
            'Job' => [
                'entity' => 'Olakunlevpn\JobSystem:Job',
                'type' => self::TO_ONE,
                'conditions' => 'job_id',
                'primary' => true
            ],
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
            'ApprovalQueue' => [
                'entity'     => 'XF:ApprovalQueue',
                'type'       => self::TO_ONE,
                'conditions' => [
                    ['content_type', '=', 'job_application'],
                    ['content_id', '=', '$application_id']
                ],
                'primary'    => true
            ]
        ];

        return $structure;
    }
}