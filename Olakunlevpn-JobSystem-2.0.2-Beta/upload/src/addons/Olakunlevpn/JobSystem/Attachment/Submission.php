<?php

namespace Olakunlevpn\JobSystem\Attachment;

use XF\Attachment\AbstractHandler;
use XF\Entity\Attachment;
use XF\Mvc\Entity\Entity;

class Submission extends AbstractHandler
{
    public function canView(Attachment $attachment, Entity $container, &$error = null)
    {
        return $container->canViewAttachments();
    }

    public function getContainerWith()
    {
        return [];
    }



    public function canManageAttachments(array $context, &$error = null)
    {
        return true;
    }

    public function onAttachmentDelete(Attachment $attachment, Entity $container = null)
    {
        if ($container)
        {
            $container->attach_count--;
            $container->save();
        }
    }

    public function getConstraints(array $context)
    {
        /** @var \XF\Repository\Attachment $attachRepo */
        $attachRepo = \XF::repository('XF:Attachment');

        $constraints = $attachRepo->getDefaultAttachmentConstraints();
        $constraints['extensions'] = ['jpg', 'jpeg', 'jpe', 'png', 'gif'];
        $constraints['count'] = 5;

        return $constraints;
    }

    public function getContainerIdFromContext(array $context)
    {
        return isset($context['submission_id']) ? intval($context['submission_id']) : null;
    }

    public function getContainerLink(Entity $container, array $extraParams = [])
    {
        return \XF::app()->router('public')->buildLink('jobs/view', $container, $extraParams);
    }

    protected function getSubmissionFromContext(array $context)
    {
        $em = \XF::em();
        return $em->find('Olakunlevpn\JobSystem:Submission', $context['submission_id']);
    }

    public function getContext(?Entity $entity = null, array $extraContext = [])
    {
        if ($entity instanceof \Olakunlevpn\JobSystem\Entity\Submission)
        {
            $extraContext['submission_id'] = $entity->submission_id;
        }
        else
        {
            throw new \InvalidArgumentException("Entity must be a submission");
        }

        return $extraContext;
    }
}
