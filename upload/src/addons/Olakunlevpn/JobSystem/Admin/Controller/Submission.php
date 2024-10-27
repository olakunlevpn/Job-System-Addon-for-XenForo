<?php

namespace Olakunlevpn\JobSystem\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\ControllerPlugin\DeletePlugin;
use XF\Mvc\ParameterBag;
use XF\Repository\TrophyRepository;

class Submission extends  AbstractController
{

    public function preDispatchController($action, ParameterBag $params)
    {
        $this->setSectionContext('jobs');
    }


    public function actionIndex(ParameterBag $params)
    {

        if ($params['submission_id'])
        {
            return $this->rerouteController(__CLASS__, 'job', $params);
        }


        $this->setSectionContext('Jobs_list');


        $page = $this->filterPage();
        $perPage = 20;


        $jobFinder = $this->finder('Olakunlevpn\JobSystem:Submission')
            ->with('User')
            ->setDefaultOrder('submitted_date', 'DESC')
            ->limitByPage($page, $perPage);


        $viewParams = [
            'submissions' => $jobFinder->fetch(),
            'total' => $jobFinder->total(),
            'page' => $page,
            'perPage' => $perPage
        ];


        return $this->view('Olakunlevpn\JobSystem:Submission', 'olakunlevpn_admin_job_system_submission',  $viewParams);
    }

    public function actionView(ParameterBag $params)
    {
        $submissionId = $params->submission_id;
        $submission = $this->assertSubmissionExists($submissionId);


        return $this->view(
            'Olakunlevpn\JobSystem:Submission\View',
            'olakunlevpn_job_system_submission_view',
            ['submission' => $submission]
        );
    }



    public function actionDecision(ParameterBag $params)
    {
        $submissionId = $params->submission_id;
        $submission = $this->assertSubmissionExists($submissionId);

        $adminComment = $this->filter('admin_comment', 'str');
        $isApproved = $this->filter('approve', 'bool');
        $isRejected = $this->filter('reject', 'bool');

        if ($isApproved) {

            if ($submission->Job->reward_type === 'db_credits') {
                $this->creditUser($submission->user_id, $submission);
            } elseif ($submission->Job->reward_type === 'trophy') {
                $this->awardTrophyToUser($submission->user_id, $submission->Job->trophy_id);
            }

            $submission->status = 'approved';
            $submission->admin_comment = $adminComment;
            $submission->save();

            $this->notifyUserJobApproved($submission);

            return $this->redirect($this->buildLink('submissions/view', $submission), \XF::phrase('olakunlevpn_job_system_submission_approved_and_credited_message'));

        } elseif ($isRejected) {
            $submission->status = 'rejected';
            $submission->admin_comment = $adminComment;
            $submission->save();

            $this->notifyUserJobRejected($submission, $adminComment);


            return $this->redirect($this->buildLink('submissions/view', $submission), \XF::phrase('olakunlevpn_job_system_submission_rejected_error'));
        }

        return $this->error(\XF::phrase('olakunlevpn_job_system_invalid_action_message'));
    }

    protected function awardTrophyToUser($userId, $trophyId)
    {
        /** @var \XF\Entity\User $user */
        $user = $this->em()->find('XF:User', $userId);
        if (!$user) {
            return;
        }

        if ($this->app->options()->enableTrophies) {

            /** @var TrophyRepository $trophyRepo */
            $trophy = $this->em()->find('XF:Trophy', $trophyId);
            if (!$trophy) {
                return;
            }
            if ($trophy && ! isset($user->Trophies[$trophy->trophy_id])) {
                $trophyRepo = $this->repository(TrophyRepository::class);
                $trophyRepo->awardTrophyToUser($trophy, $user);
            }
        }
    }



    protected function creditUser($userId, $submission)
    {
        /** @var \XF\Entity\User $user */
        $user = $this->em()->find('XF:User', $userId);
        if (!$user) {
            throw new \XF\PrintableException(\XF::phrase('olakunlevpn_job_system_user_not_found_exception'));
        }

        /** @var \DBTech\Credits\Repository\EventTrigger $eventTriggerRepo */
        $eventTriggerRepo = \XF::app()->repository('DBTech\Credits:EventTrigger');

        $adjustHandler = $eventTriggerRepo->getHandler('adjust');

        $visitor = \XF::visitor();

        $adjustHandler->setOption('adminOverride', true)
        ->apply($user->user_id, [
            'currency_id' => $submission->Job->reward_currency,
            'multiplier' => $submission->Job->reward_amount,
            'message' => \XF::language()->renderPhrase(\XF::phrase('olakunlevpn_job_system_reward_for_job_completion')),
            'source_user_id' => $visitor->user_id
        ], $user);

    }



    public function actionDelete(ParameterBag $params)
    {
        /** @var \Olakunlevpn\JobSystem\Entity\Submission $submission */
        $submission = $this->assertSubmissionExists($params->submission_id);

            $plugin = $this->plugin(DeletePlugin::class);
            return $plugin->actionDelete(
                $submission,
                $this->buildLink('submissions/delete', $submission),
                $this->buildLink('submissions/view', $submission),
                $this->buildLink('submissions'),
                \XF::phrase('olakunlevpn_job_system_submission_deletion_description', [
                    'submission_id' => $submission->submission_id,
                    'job_title' => $submission->Job->title
                ])            );
    }


    protected function notifyUserJobRejected($submission, $adminComment = '')
    {
        $extraData = [
            'adminComment' => $adminComment
        ];

        $this->notifyUserJobStatus($submission, 'rejected', $extraData);
    }

// For approved submissions
    protected function notifyUserJobApproved($submission)
    {
        $extraData = [
         ];

        $this->notifyUserJobStatus($submission, 'approved', $extraData);
    }



    protected function notifyUserJobStatus($submission, $action, array $extraData = [])
    {
        /** @var \XF\Repository\UserAlert $userAlertRepo */
        $userAlertRepo = \XF::app()->repository('XF:UserAlert');

        $user = \XF::app()->find('XF:User', $submission->user_id);

        if (!$user) {
            return;
        }

        $defaultExtraData = [
            'depends_on_addon_id' => 'Olakunlevpn/JobSystem',
            'jobTitle' => $submission->Job->title,
        ];

        $extraData = array_merge($defaultExtraData, $extraData);

        $userAlertRepo->alert(
            $user,
            0,
            '',
            'job_submission_tasks',
            $submission->submission_id,
            $action,
            $extraData
        );
    }




    protected function assertSubmissionExists($submissionId)
    {
        $submission = $this->em()->find('Olakunlevpn\JobSystem:Submission', $submissionId);
        if (!$submission) {
            throw $this->exception($this->notFound(\XF::phrase('requested_submission_not_found')));
        }

        return $submission;
    }



}