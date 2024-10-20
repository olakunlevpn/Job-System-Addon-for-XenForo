<?php

namespace Olakunlevpn\JobSystem\ApprovalQueue;


use XF;
use XF\ApprovalQueue\AbstractHandler;
use XF\Mvc\Entity\Entity;
use XF\Repository\UserAlert;

class WithdrawRequest extends AbstractHandler
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

    /**
     * @return array
     */
    public function getEntityWith()
    {
        return ['User', 'Currency'];
    }

    /**
     * @param \Olakunlevpn\JobSystem\Entity\WithdrawRequest $request
     */
    public function actionApprove(\Olakunlevpn\JobSystem\Entity\WithdrawRequest $request)
    {
        $this->quickUpdate($request, 'status', 'completed');

        /** @var UserAlert $alertRepo */
        $alertRepo = XF::repository('XF:UserAlert');
        $alertRepo->alertFromUser($request->User, $request->User, 'job_submission_withdraw', $request->withdraw_request_id, 'completed');
    }

    /**
     * @param \Olakunlevpn\JobSystem\Entity\WithdrawRequest $request
     */
    public function actionDelete(\Olakunlevpn\JobSystem\Entity\WithdrawRequest $request)
    {
        $this->quickUpdate($request, 'status', 'rejected');

        /** @var UserAlert $alertRepo */
        $alertRepo = XF::repository('XF:UserAlert');
        $alertRepo->alertFromUser($request->User, $request->User, 'job_submission_withdraw', $request->withdraw_request_id, 'rejected');
    }
}