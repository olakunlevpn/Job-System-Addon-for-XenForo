<?php

namespace Olakunlevpn\JobSystem\Alert;


use XF\Alert\AbstractHandler;

class WithdrawRequest extends AbstractHandler
{
    /**
     * @return array
     */
    public function getEntityWith()
    {
        return ['User', 'Currency'];
    }
}