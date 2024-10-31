<?php

namespace Olakunlevpn\JobSystem\Helper;

use XF\Mvc\Entity\Entity;
use XF;

class JobSystemHelper
{
    /**
     * Prepare job details to be rendered as raw HTML content.
     * @param Entity|\Olakunlevpn\JobSystem\Entity\Job $job
     */
    public static function prepareJobDetails(Entity $job)
    {
        return XF::app()->bbCode()->render($job->details, 'html', 'job', $job);
    }


    public static function processPageContent(string $content): string
    {
        return str_replace('{$xf.options.boardTitle}', XF::options()->boardTitle, $content);
    }


}