<?php

namespace Olakunlevpn\JobSystem\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Submission extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_job_system_submissions';  // Table name
        $structure->shortName = 'Olakunlevpn\JobSystem:Submission';
        $structure->primaryKey = 'submission_id';

        // Fields (columns) definitions
        $structure->columns = [
            'submission_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'job_id' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true, 'default' => \XF::visitor()->user_id],
            'submission_data' => ['type' => self::STR, 'required' => true],
            'admin_comment' => ['type' => self::STR, 'nullable' => true],
            'status' => ['type' => self::STR, 'allowedValues' => ['pending', 'approved', 'rejected'], 'default' => 'pending'],
            'submitted_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'attachment_hash' => ['type' => self::STR,  'maxLength' => 255, 'nullable' => true],
        ];

        // Relations
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
            'Attachments' => [
                'entity' => 'XF:Attachment',
                'type' => self::TO_MANY,
                'conditions' => [
                    ['content_type', '=', 'job_submission_tasks'],
                    ['content_id', '=', '$submission_id']
                ],
                'with' => 'Data',
                'order' => 'attach_date'
            ]



        ];

        return $structure;
    }

    public function canUploadAndManageAttachments(): bool
    {
        // Add logic to determine whether attachments can be managed for this submission
        // You might want to check the user's permissions, the job status, etc.

        // For now, let's assume all users can upload and manage attachments for this submission:
        return true;
    }


}
