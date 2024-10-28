<?php

namespace Olakunlevpn\JobSystem\Alert;

use XF\Alert\AbstractHandler;
use XF\Mvc\Entity\Entity;
use XF\Entity\UserAlert;

class JobHandler extends AbstractHandler
{

    public function canViewContent(Entity $entity, &$error = null)
    {
       return true;
    }

    public function getEntityWith(): array
    {
        return ['Job'];
    }


    public function getTemplateName($action): string
    {
        return 'public:alert_job_submission_tasks_' . $action;
    }


    public function getPushTemplateName($action): string
    {
        return 'public:push_job_submission_tasks_' . $action;
    }


    public function getTemplateData($action, UserAlert $alert, ?Entity $content = null): array
    {

        /** @var \Olakunlevpn\JobSystem\Entity\Submission $content */
        $item = parent::getTemplateData($action, $alert, $content);

        switch ($action)
        {
            case 'approved':
                $item['jobTitle'] = $content->Job->title;
                $item['message'] = \XF::phrase('olakunlevpn_job_system_submission_has_been_approved_message');
                break;

            case 'rejected':
                $item['jobTitle'] = $content->Job->title;
                $item['message'] = \XF::phrase('olakunlevpn_job_system_submission_has_been_rejected_message');
                break;

            case 'pending':
                $item['jobTitle'] = $content->Job->title;
                $item['message'] = \XF::phrase('olakunlevpn_job_system_submission_is_pending_message');
                break;



            default:
                $item['message'] = \XF::phrase('olakunlevpn_job_system_submission_update_message');
                break;
        }

        return $item;
    }





    public function getOptOutActions()
    {
        return ['rejected', 'approved'];
    }


    public function getOptOutDisplayOrder(): int
    {
        return 10000;
    }


}