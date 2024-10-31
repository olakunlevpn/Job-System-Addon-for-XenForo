<?php

namespace Olakunlevpn\JobSystem\Traits;

use XF;
use XF\Mvc\Reply\Exception;
use DBTech\Credits\XF\Entity\User;

trait JobSystemPermissionsTrait
{
    /**
     * @return void
     * @throws Exception
     */
    public function ensureWithdrawalFeatureIsEnabled(): void
    {
        if (!XF::options()->olakunlevpn_job_system_enable_withdrawal) {
            throw $this->exception($this->noPermission());
        }
    }

    /**
     * @return XF\Entity\User
     * @throws Exception
     */
    public function ensureUserIsLoggedInAndHaveTheRightPermission(): XF\Entity\User
    {
        $visitor = XF::visitor();
        if (!$visitor || !$visitor->canViewDbtechCredits()) {
            throw $this->exception($this->noPermission());
        }

        return $visitor;
    }
}