<?php

namespace Olakunlevpn\JobSystem\Service;


use XF;
use Olakunlevpn\JobSystem\Entity\Submission;

class JobSystemNotificationService
{
    public static function notifyUserJobPending(Submission $submission, $user): void
    {
        $extraData = [
            'message' => \XF::phrase('olakunlevpn_job_system_submission_pending_review')
        ];
        self::notifyUserJobStatus($submission, 'pending', $user, $extraData);
    }

    public static function notifyModeratoryJobPending(Submission $submission, $user): void
    {
        $extraData = [
            'message' => \XF::phrase('olakunlevpn_job_system_submission_pending_review_moderator')
        ];
        self::notifyUserJobStatus($submission, 'moderator', $user,  $extraData);
    }



    public static function notifyUserJobStatus(Submission $submission, string $action, $user, array $extraData = [])
    {
        $userAlertRepo = XF::app()->repository('XF:UserAlert');


        if (!$user) {
            return;
        }

        $defaultExtraData = [
            'depends_on_addon_id' => 'Olakunlevpn/JobSystem',
            'jobTitle' => $submission->Job->title,
            'username' => \XF::visitor()->username,
            'url' => \XF::app()->router('admin')->buildLink('submissions/view', ['submission_id' => $submission->submission_id]),
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