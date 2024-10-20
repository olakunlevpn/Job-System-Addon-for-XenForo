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
                $item['message'] = "Your job submission has been approved.";
                break;

            case 'rejected':
                $item['jobTitle'] = $content->Job->title;
                $item['message'] = "Your job submission has been rejected.";
                break;

            case 'pending':
                $item['jobTitle'] = $content->Job->title;
                $item['message'] = "Your job submission is pending and currently under review.";
                break;



            default:
                $item['message'] = "There has been an update on your job submission.";
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