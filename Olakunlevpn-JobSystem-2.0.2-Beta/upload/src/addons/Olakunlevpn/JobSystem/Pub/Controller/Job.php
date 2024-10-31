<?php

namespace Olakunlevpn\JobSystem\Pub\Controller;



use XF;
use XF\Mvc\ParameterBag;
use DBTech\Credits\XF\Entity\User;
use XF\ControllerPlugin\Attachment;
use XF\Mvc\Reply\Exception;
use XF\Pub\Controller\AbstractController;
use Olakunlevpn\JobSystem\Entity\Submission;
use Olakunlevpn\JobSystem\Entity\WithdrawRequest;
use Olakunlevpn\JobSystem\Helper\JobSystemHelper;
use Olakunlevpn\JobSystem\Traits\JobSystemPermissionsTrait;
use Olakunlevpn\JobSystem\Traits\JobSystemRepositoriesTrait;
use Olakunlevpn\JobSystem\Service\JobSystemNotificationService;

class Job extends AbstractController
{

    use JobSystemRepositoriesTrait;
    use JobSystemPermissionsTrait;


    protected function preDispatchController($action, ParameterBag $params)
    {
        if (!\XF::options()->olakunlevpn_job_system_enabled) {
            throw $this->exception($this->noPermission());
        }

        parent::preDispatchController($action, $params);

    }


    public function actionIndex()
    {

        $pageContent = JobSystemHelper::processPageContent($this->options()->olakunlevpn_job_system_welcome_page_content);


        return $this->view(
            'Olakunlevpn\JobSystem:Job\Index',
            'olakunlevpn_job_system_welcome',
            [
                'page_title' => $this->options()->olakunlevpn_job_system_welcome_page_title,
                'page_content' => $pageContent,
                'page_content_faqs' => $this->options()->olakunlevpn_job_system_welcome_page_faq,
                'page_cta_title' => $this->options()->olakunlevpn_job_system_welcome_page_cta_title,
                'page_cta_button_text' => $this->options()->olakunlevpn_job_system_welcome_page_cta_button
            ]
        );
    }



    public function actionList()
    {
        $page = $this->filterPage();
        $perPage = 10;

        $rewardType = $this->filter('reward_type', 'str');
        $direction = $this->filter('direction', 'str');



        $jobFinder = $this->getJobRepo()->findActiveJobs();

        $this->getJobRepo()->applyRewardsFilter($rewardType, $jobFinder);

        $this->getJobRepo()->applyOrderByFilter($jobFinder, $direction);


        $totalJobs = $jobFinder->total();
        $currenciesList = $this->getCurrencyRepo()->getCurrencyTitlePairs();


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
                'currenciesList' => $currenciesList,
                'filters' => [
                    'reward_type' => $rewardType ?? 'any',
                    'direction' => $direction ?? 'desc'
                ]

            ]);
    }


    public function actionView(ParameterBag $params)
    {

        $visitor = \XF::visitor();
        $job = $this->assertJobExists($params->job_id);



        if (\XF::options()->olakunlevpn_job_system_pre_approve_required && !$job->Application)
        {
            throw $this->exception($this->noPermission());
        }

        $submission = $this->em()->create('Olakunlevpn\JobSystem:Submission');
        $submission->job_id = $job->job_id;
        $submission->user_id = $visitor->user_id;


        $submissionProcess = $this->em()->findOne(Submission::class, [
            'job_id' => $job->job_id,
            'user_id' => $visitor->user_id
        ]);


        /** @var \XF\Repository\Attachment $attachmentRepo */
        $attachmentRepo = $this->repository('XF:Attachment');
        $attachmentData = $attachmentRepo->getEditorData(
            'job_submission_tasks',
            $submission
        );

        $job->details = JobSystemHelper::prepareJobDetails($job);

        $viewParams = [
            'job' => $job,
            'existingApplication' => $job->Application,
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


    public function actionSubmit(ParameterBag $params)
    {
        /** @var User $visitor */
        $this->ensureUserIsLoggedInAndHaveTheRightPermission();

        $jobId = $params->job_id;
        $job = $this->assertJobExists($jobId);

        $input = $this->filter([
            'submission_text' => 'str',
            'submission_url' => 'str',
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

        $hash = $this->filter('attachment_hash', 'str');
        $submission->submitted_date = \XF::$time;
        $submission->attachment_hash =  $hash;


        $submission->save();


        if ($job->has_attachment && $submission->canUploadAndManageAttachments()) {
            $inserter = $this->service(\XF\Service\Attachment\PreparerService::class);
            $associated = $inserter->associateAttachmentsWithContent($hash, 'job_submission_tasks', $submission->submission_id);

            if (!$associated) {
                $submission->fastUpdate('attach_count', $submission->attach_count + $associated);
            }

        }

        JobSystemNotificationService::notifyUserJobPending($submission);

        return $this->redirect($this->buildLink('jobs/view', $job), \XF::phrase('olakunlevpn_job_system_submission_received'));
    }



    public function actionApply(ParameterBag $params)
    {
        $this->assertRegistrationRequired();

        $jobId = $params->job_id;
        $visitor = \XF::visitor();
        $job = $this->assertJobExists($jobId);

        if ($job->Application) {
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


    /**
     * @throws Exception
     */
    public function actionApplyConfirmation(ParameterBag $params)
    {
        $this->assertRegistrationRequired();

        $jobId = $params->job_id;
        $job = $this->assertJobExists($jobId);


        if (!$job->Application) {
            return $this->view(
                'Olakunlevpn\JobSystem:Job\ApplyConfirmation',
                'olakunlevpn_job_system_apply_confirmation',
                ['job' => $job]
            );
        }

        if ($job->Application && $job->Application->status === 'pending') {
            return $this->message('Your application has been submitted and is pending review.');
        }


        return $this->redirect($this->buildLink('jobs/view', $job));
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

        $currency  = $this->getCurrencyRepo()
            ->finder('DBTech\Credits:Currency')
            ->where('currency_id', $input['currency_id'])
            ->fetchOne();



        $userCreditAmount = $visitor->{$currency->column};


        if ($userCreditAmount < $input['amount'])
        {

            return $this->noPermission(\XF::phrase('olakunlevpn_job_system_enter_valid_amount', [
                'balance' => number_format($userCreditAmount, 2),
                'currency' =>  $currency ? $currency->title : null
            ]));
        }

        if ($input['amount'] < $this->options()->olakunlevpn_job_system_minimum_withdrawal) {
            return $this->error(
                \XF::phrase('olakunlevpn_job_system_min_withdrawal_amount', [
                    'amount' => $this->options()->olakunlevpn_job_system_minimum_withdrawal
                ])
            );
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



    protected function assertJobExists($jobId)
    {
        return $this->assertRecordExists('Olakunlevpn\JobSystem:Job', $jobId);
    }




}