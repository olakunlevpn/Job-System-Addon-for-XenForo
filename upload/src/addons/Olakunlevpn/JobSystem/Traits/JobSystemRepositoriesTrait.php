<?php

namespace Olakunlevpn\JobSystem\Traits;

trait JobSystemRepositoriesTrait
{
    protected function getJobRepo()
    {
        return $this->repository('Olakunlevpn\JobSystem:Job');
    }

    protected function getCurrencyRepo()
    {
        return $this->repository('DBTech\Credits:Currency');
    }
}