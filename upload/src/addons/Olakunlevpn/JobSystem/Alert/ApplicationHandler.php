<?php

namespace Olakunlevpn\JobSystem\Alert;

use XF;
use XF\Alert\AbstractHandler;
use XF\Entity\UserAlert;
use XF\Mvc\Entity\Entity;


class ApplicationHandler extends AbstractHandler
{


    /**
     * @return array
     */
    public function getEntityWith()
    {
        return ['User', 'Job'];
    }

    public function getTemplateName($action): string
    {
        return 'public:olakunlevpn_job_system_alert_application_' . $action;
    }

    public function getPushTemplateName($action): string
    {
        return 'public:olakunlevpn_job_system_push_application_' . $action;
    }

    /**
     * Provides data for use in the alert template.
     *
     * @param string $action
     * @param UserAlert $alert
     * @param Entity|null $content
     * @return array
     */
    public function getTemplateData($action, UserAlert $alert, ?Entity $content = null): array
    {
        /** @var \Olakunlevpn\JobSystem\Entity\Application $content */
        $data = parent::getTemplateData($action, $alert, $content);

        switch ($action) {
            case 'approved':
                $data['jobTitle'] = $content->Job->title;
                $data['message'] = \XF::phrase('olakunlevpn_job_system_application_approved_message');
                break;

            case 'rejected':
                $data['jobTitle'] = $content->Job->title;
                $data['adminComment'] = $content->admin_comment;
                $data['message'] = \XF::phrase('olakunlevpn_job_system_application_rejected_message');
                break;

            default:
                $data['message'] = \XF::phrase('olakunlevpn_job_system_application_update_message');
                break;
        }

        return $data;
    }


    public function getOptOutActions()
    {
        return ['approved', 'rejected'];
    }


    public function getOptOutDisplayOrder(): int
    {
        return 10000;
    }



}