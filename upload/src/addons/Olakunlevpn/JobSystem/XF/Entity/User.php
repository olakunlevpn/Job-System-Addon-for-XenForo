<?php

namespace Olakunlevpn\JobSystem\XF\Entity;


use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;


/**
* @extends \XF\Entity\User
*
*/

class User extends XFCP_User
{

    /**
     * @return bool
     */
    public function canViewJob()
    {
        return $this->hasPermission('olakunlevpn_jobsystem', 'view');
    }

    /**
     * @return bool
     */
    public function canInitiateWithdrawal()
    {
        return $this->hasPermission('olakunlevpn_jobsystem', 'withdraw');
    }


    /**
     * @return bool
     */
    public function canApplyForJob()
    {
        return $this->hasPermission('olakunlevpn_jobsystem', 'apply');
    }


    /**
     * @return bool
     */
    public function canInlineMod()
    {
        return $this->hasPermission('olakunlevpn_jobsystem_mod', 'moderate');
    }






}