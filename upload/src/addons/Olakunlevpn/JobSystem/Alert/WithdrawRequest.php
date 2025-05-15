<?php

namespace Olakunlevpn\JobSystem\Alert;


use Olakunlevpn\JobSystem\Helper\JobSystemHelper;
use XF\Alert\AbstractHandler;

class WithdrawRequest extends AbstractHandler
{
    /**
     * @return array
     */
    public function getEntityWith()
    {
        $relations = ['User'];

        if (JobSystemHelper::ensureDbCreditAddonInstalled()) {
            $relations[] = 'Currency';
        }

        return $relations;
    }
}