<?php

namespace Olakunlevpn\JobSystem\ApprovalQueue;

use XF;
use XF\ApprovalQueue\AbstractHandler;
use XF\Mvc\Entity\Entity;
use XF\Repository\UserAlert;


class Application extends AbstractHandler
{
    /**
     * @param Entity $content
     * @param null   $error
     *
     * @return bool
     */
    protected function canActionContent(Entity $content, &$error = null)
    {
        return XF::visitor()->is_super_admin;
    }

    public function getEntityWith()
    {
        return ['User', 'Job'];
    }

    public function actionApprove(\Olakunlevpn\JobSystem\Entity\Application $application)
    {
        $this->quickUpdate($application, 'status', 'approved');

        /** @var UserAlert $alertRepo */
        $alertRepo = \XF::repository('XF:UserAlert');
        $alertRepo->alertFromUser($application->User, $application->User, 'job_application', $application->application_id, 'approved');
    }

    public function actionDelete(\Olakunlevpn\JobSystem\Entity\Application $application)
    {
        $this->quickUpdate($application, 'status', 'rejected');

        /** @var UserAlert $alertRepo */
        $alertRepo = \XF::repository('XF:UserAlert');
        $alertRepo->alertFromUser($application->User, $application->User, 'job_application', $application->application_id, 'rejected');
    }
}