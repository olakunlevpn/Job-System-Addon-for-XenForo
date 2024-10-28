<?php

namespace Olakunlevpn\JobSystem\Pub\Controller;


use XF;
use XF\ControllerPlugin\ErrorPlugin;
use XF\Mvc\ParameterBag;
use DBTech\Credits\XF\Entity\User;
use XF\ControllerPlugin\Attachment;
use XF\Mvc\Reply\Exception;
use XF\Pub\Controller\AbstractController;
use Olakunlevpn\JobSystem\Entity\WithdrawRequest;



class Job extends AbstractController
{


    protected function preDispatchController($action, ParameterBag $params)
    {
        if (!\XF::options()->olakunlevpn_job_system_enabled) {
            throw $this->exception($this->noPermission());
        }

        parent::preDispatchController($action, $params);

    }


    public function actionIndex()
    {

        $jobs = $this->getJobRepo()->findActiveJobs()->fetch();
        $pageContent = str_replace('{$xf.options.boardTitle}', \XF::options()->boardTitle, $this->options()->olakunlevpn_job_system_welcome_page_content);


        return $this->view(
            'Olakunlevpn\JobSystem:Job\Index',
            'olakunlevpn_job_system_welcome',
            ['jobs' => $jobs,
                'page_title' => $this->options()->olakunlevpn_job_system_welcome_page_title,
                'page_content' => $pageContent,
                'page_content_faqs' => $this->options()->olakunlevpn_job_system_welcome_page_faq,
                'page_cta_title' => $this->options()->olakunlevpn_job_system_welcome_page_cta_title,
                'page_cta_button_text' => $this->options()->olakunlevpn_job_system_welcome_page_cta_button
            ]
        );
    }

    public function actionView(ParameterBag $params)
    {

        $jobId = $params->job_id;
        if ($jobId) {
            $job = $this->assertJobExists($jobId);
        } else {
            return $this->message(\XF::phrase('olakunlevpn_job_system_job_not_found'));
        }

        $visitor = \XF::visitor();


        $submission = $this->em()->create('Olakunlevpn\JobSystem:Submission');
        $submission->job_id = $jobId;
        $submission->user_id = $visitor->user_id;

        $submissionProcess = $this->em()->findOne('Olakunlevpn\JobSystem:Submission', [
            'job_id' => $jobId,
            'user_id' => $visitor->user_id
        ]);

        $existingApplication = $this->em()->findOne('Olakunlevpn\JobSystem:Application', [
            'job_id' => $jobId,
            'user_id' => $visitor->user_id
        ]);


        $attachmentRepo = $this->repository('XF:Attachment');
        $attachmentData = $attachmentRepo->getEditorData('job_submission_tasks', $submission);

        $job->details = \XF::app()->bbCode()->render($job->details, 'html', 'job', $job);

        $viewParams = [
            'job' => $job,
            'existingApplication' => $existingApplication,
            'existingSubmission' => $submissionProcess,
            'attachmentData' => $attachmentData,
            'submission' => $submission,
            'submissionProcess' => $submissionProcess
        ];

        return $this->view(
            'Olakunlevpn\JobSystem:Job\Index',
            'olakunlevpn_job_system_view',
            $viewParams
        );
    }



    public function actionList()
    {
        $page = $this->filterPage();
        $perPage = 10;

        $jobFinder = $this->getJobRepo()->findActiveJobs();
        $totalJobs = $jobFinder->total();



        $jobs = $jobFinder
            ->limitByPage($page, $perPage)
            ->fetch();

        return $this->view(
            'Olakunlevpn\JobSystem:Job\Index',
            'olakunlevpn_job_system_index',
            [
                'jobs' => $jobs,
                'page' => $page,
                'perPage' => $perPage,
                'total' => $totalJobs,
            ]);
    }


    public function actionSubmit(ParameterBag $params)
    {
        /** @var User $visitor */
        $this->ensureUserIsLoggedInAndHaveTheRightPermission();

        $jobId = $params->job_id;
        $job = $this->assertJobExists($jobId);

        $input = $this->filter([
            'submission_text' => 'str',
            'submission_url' => 'str',
            'attachment_hash' => 'str',
        ]);


        $submission = \XF::app()->em()->create('Olakunlevpn\JobSystem:Submission');
        $submission->job_id = $jobId;
        $submission->user_id = \XF::visitor()->user_id;


        if ($job->type == 'text') {
            if (empty($input['submission_text'])) {
                return $this->error(\XF::phrase('olakunlevpn_job_system_enter_response'));
            }
            $submission->submission_data = $input['submission_text'];
        } else {
            if (empty($input['submission_url'])) {
                return $this->error(\XF::phrase('olakunlevpn_job_system_enter_url'));
            }

            if (!filter_var($input['submission_url'], FILTER_VALIDATE_URL)) {
                return $this->error(\XF::phrase('olakunlevpn_job_system_enter_valid_url'));
            }

            $submission->submission_data = $input['submission_url'];
        }


        if($job->has_attachment)
        {
            $inserter = $this->service(\XF\Service\Attachment\PreparerService::class);
            $associated = $inserter->associateAttachmentsWithContent($input['attachment_hash'], 'job_submission_tasks', $submission->submission_id);
            if (!$associated)
            {
                return $this->error(\XF::phrase('olakunlevpn_job_system_upload_error'));
            }
        }



        $submission->submitted_date = \XF::$time;
        $submission->attachment_hash = $input['attachment_hash'];
        $submission->save();


        $this->notifyUserJobPending($submission);


        return $this->redirect($this->buildLink('jobs/view', $job), \XF::phrase('olakunlevpn_job_system_submission_received'));
    }




    /**
     * @throws Exception
     */
    public function actionApplyConfirmation(ParameterBag $params)
    {
        $this->assertRegistrationRequired();

        $jobId = $params->job_id;
        $visitor = \XF::visitor();
        $job = $this->assertJobExists($jobId);

        $application = $this->em()->findOne('Olakunlevpn\JobSystem:Application', [
            'job_id' => $jobId,
            'user_id' => $visitor->user_id
        ]);

        if (!$application) {
            return $this->view(
                'Olakunlevpn\JobSystem:Job\ApplyConfirmation',
                'olakunlevpn_job_system_apply_confirmation',
                ['job' => $job]
            );
        }

        if ($application->status === 'pending') {
            return $this->message('Your application has been submitted and is pending review.');
        }


        return $this->redirect($this->buildLink('jobs/view', $job));
    }


    public function actionApply(ParameterBag $params)
    {
        $this->assertRegistrationRequired();

        $jobId = $params->job_id;
        $visitor = \XF::visitor();
        $job = $this->assertJobExists($jobId);

        $existingApplication = $this->em()->findOne('Olakunlevpn\JobSystem:Application', [
            'job_id' => $job->job_id,
            'user_id' => $visitor->user_id
        ]);

        if ($existingApplication) {
            return $this->message('You have already applied for this job.');
        }


        $application = $this->em()->create('Olakunlevpn\JobSystem:Application');
        $application->job_id = $job->job_id;
        $application->user_id = $visitor->user_id;
        $application->status = \XF::options()->olakunlevpn_job_system_pre_approve_required   ? 'pending' : 'approved';
        $application->approved_date = \XF::$time;
        $application->save();

        return $this->message('Your application has been submitted and is pending review.');
    }




    protected function notifyUserJobPending($submission)
    {
        $extraData = [
            'message' => "Your job submission is currently pending review. Please wait for further updates."
        ];

        $this->notifyUserJobStatus($submission, 'pending', $extraData);
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


    /**
     * @throws Exception
     */
    public function actionCompleted()
    {

        /** @var User $visitor */
        $this->ensureUserIsLoggedInAndHaveTheRightPermission();

        $userId = \XF::visitor()->user_id;
        $page = $this->filterPage();
        $perPage = 10;

        $jobFinder = $this->getJobRepo()->findUserApprovedSubmissions($userId);
        $totalJobs = $jobFinder->total();

        $jobs = $jobFinder
            ->limitByPage($page, $perPage)
            ->fetch();

        return $this->view(
            'Olakunlevpn\JobSystem:Job\Completed',
            'olakunlevpn_job_system_completed',
            [
                'jobs' => $jobs,
                'page' => $page,
                'perPage' => $perPage,
                'total' => $totalJobs,
            ]
        );
    }


    public function actionPending()
    {
        /** @var User $visitor */
        $this->ensureUserIsLoggedInAndHaveTheRightPermission();


        $userId = \XF::visitor()->user_id;
        $page = $this->filterPage();
        $perPage = 10;

        $jobFinder = $this->getJobRepo()->findUserPendingSubmissions($userId);
        $totalJobs = $jobFinder->total();



        $jobs = $jobFinder
            ->limitByPage($page, $perPage)
            ->fetch();

        return $this->view(
            'Olakunlevpn\JobSystem:Job\Pending',
            'olakunlevpn_job_system_pending',
            [
                'jobs' => $jobs,
                'page' => $page,
                'perPage' => $perPage,
                'total' => $totalJobs,
            ]
        );
    }



    /**
     * @return XF\Mvc\Reply\AbstractReply|XF\Mvc\Reply\Error|XF\Mvc\Reply\View
     */
    public function actionWithdraw()
    {
        $this->ensureUserIsLoggedInAndHaveTheRightPermission();

        $this->ensureWithdrawalFeatureIsEnabled();


        $currenciesList = $this->getCurrencyRepo()->getCurrencyTitlePairs();

        $withdrawProfiles = preg_split('/\s/', $this->options()->olakunlevpn_job_system_paymentProfiles, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($currenciesList))
        {
            return $this->noPermission();
        }

        $viewParams = [
            'currencies'       => $currenciesList,
            'withdrawProfiles' => $withdrawProfiles,
            'pageSelected'     => 'withdraw'
        ];

        return $this->view('Olakunlevpn\JobSystem:Job\Withdraw\Index', 'olakunlevpn_job_system_withdrawal_index', $viewParams);
    }



    /**
     * @return XF\Mvc\Reply\AbstractReply|XF\Mvc\Reply\Error|XF\Mvc\Reply\Redirect|XF\Mvc\Reply\View
     * @throws XF\PrintableException
     */
    public function actionWithdrawCreate()
    {
        $visitor = $this->ensureUserIsLoggedInAndHaveTheRightPermission();

        $this->ensureWithdrawalFeatureIsEnabled();



        $withdrawProfiles = preg_split('/\s/', $this->options()->olakunlevpn_job_system_paymentProfiles, -1, PREG_SPLIT_NO_EMPTY);

        $input = $this->filter([
            'currency_id'          => 'uint',
            'payment_profile'      => 'str',
            'payment_profile_data' => 'str',
            'amount'               => 'uint'
        ]);

        $currency = $this->em()->find('DBTech\Credits:Currency', $input['currency_id']);
        if ($currency && !$currency->canView())
        {
            return $this->noPermission();
        }

        if (empty($input['payment_profile_data']) || !in_array($input['payment_profile'], $withdrawProfiles))
        {
            return $this->noPermission();
        }


        if (strlen($input['payment_profile_data']) < $this->options()->olakunlevpn_job_system_minimum_wallet_lenght) {
            return $this->error(\XF::phrase('olakunlevpn_job_system_min_wallet_length', ['length' => $this->options()->olakunlevpn_job_system_minimum_wallet_lenght]));
        }


        $userCreditAmount = $visitor->get($currency->column);

        if ($input['amount'] < $this->options()->olakunlevpn_job_system_minimum_withdrawal) {
            return $this->error(
                \XF::phrase('olakunlevpn_job_system_min_withdrawal_amount', [
                    'amount' => $this->options()->olakunlevpn_job_system_minimum_withdrawal
                ])
            );
        }


        if ($userCreditAmount < $input['amount'])
        {
            return $this->noPermission(\XF::phrase('olakunlevpn_job_system_enter_valid_amount'));
        }

        if ($input['amount'] < $this->options()->olakunlevpn_job_system_minimum_withdrawal)
        {
            return $this->error(\XF::phrase('olakunlevpn_job_system_min_withdrawal_amount', ['amount' => $this->options()->olakunlevpn_job_system_minimum_withdrawal]));
        }


        /** @var WithdrawRequest $withdrawRequest */
        $withdrawRequest = $this->em()->create('Olakunlevpn\JobSystem:WithdrawRequest');
        $withdrawRequest->bulkSet($input);
        $withdrawRequest->user_id = $visitor->user_id;
        $withdrawRequest->save();

        return $this->redirect($this->buildLink('jobs/withdraw-list'));


    }


    /**
     * @return XF\Mvc\Reply\View
     * @throws Exception
     */
    public function actionWithdrawList(): \XF\Mvc\Reply\View
    {

        /** @var User $visitor */
        $this->ensureUserIsLoggedInAndHaveTheRightPermission();

        $this->ensureWithdrawalFeatureIsEnabled();

        $page = $this->filterPage();
        $perPage = 10;

        $withdrawRequestsFinder = $this->finder('Olakunlevpn\JobSystem:WithdrawRequest')
            ->where('user_id', \XF::visitor()->user_id)
            ->order('withdraw_request_id', 'desc');

        $totalRequests = $withdrawRequestsFinder->total();
        $withdrawRequests = $withdrawRequestsFinder
            ->limitByPage($page, $perPage)
            ->fetch();

        $viewParams = [
            'withdrawRequests' => $withdrawRequests,
            'pageSelected' => 'withdrawList',
            'page' => $page,
            'perPage' => $perPage,
            'total' => $totalRequests
        ];

        return $this->view('Olakunlevpn\JobSystem:Job\Withdraw\List', 'olakunlevpn_job_system_withdrawal_list', $viewParams);

    }


    /**
     * @return void
     * @throws Exception
     */
    public function ensureWithdrawalFeatureIsEnabled(): void
    {
        if ( ! $this->options()->olakunlevpn_job_system_enable_withdrawal) {
            throw $this->exception($this->noPermission());
        }
    }

    /**
     * @return User
     * @throws XF\Mvc\Reply\Exception
     */
    public function ensureUserIsLoggedInAndHaveTheRightPermission(): User
    {
        /** @var User $visitor */
        $visitor = XF::visitor();

        if ( ! $visitor || ! $visitor->canViewDbtechCredits()) {
            throw $this->exception($this->noPermission());
        }

        return $visitor;
    }


    protected function getJobRepo()
    {
        return $this->repository('Olakunlevpn\JobSystem:Job');
    }


    protected function assertJobExists($jobId)
    {
        return $this->assertRecordExists('Olakunlevpn\JobSystem:Job', $jobId);
    }


    protected function getAttachmentRepo()
    {
        return $this->repository('XF:Attachment');
    }



    protected function getCurrencyRepo()
    {
        return $this->repository('DBTech\Credits:Currency');
    }



}