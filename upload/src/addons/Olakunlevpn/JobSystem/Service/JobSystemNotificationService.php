<?php

namespace Olakunlevpn\JobSystem\Service;


use XF;
use Olakunlevpn\JobSystem\Entity\Submission;

class JobSystemNotificationService
{
    public static function notifyUserJobPending(Submission $submission)
    {
        $extraData = [
            'message' => \XF::phrase('olakunlevpn_job_system_submission_pending_review')
        ];
        self::notifyUserJobStatus($submission, 'pending', $extraData);
    }

    public static function notifyUserJobStatus(Submission $submission, string $action, array $extraData = [])
    {
        $userAlertRepo = XF::app()->repository('XF:UserAlert');
        $user = XF::app()->find('XF:User', $submission->user_id);

        if (!$user) {
            return;
        }

        $defaultExtraData = [
            'depends_on_addon_id' => 'Olakunlevpn/JobSystem',
            'jobTitle' => $submission->Job->title,
        ];

        $extraData = array_merge($defaultExtraData, $extraData);

        $userAlertRepo->alert(
            $user,
            0,
            '',
            'job_submission_tasks',
            $submission->submission_id,
            $action,
            $extraData
        );
    }
}