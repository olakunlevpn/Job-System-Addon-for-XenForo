<?php

namespace Olakunlevpn\JobSystem\Pub\Controller;



use XF;
use XF\Entity\User as XFUser;
use XF\Html\Renderer\BbCode;
use XF\Mvc\ParameterBag;
use Olakunlevpn\JobSystem\XF\Entity\User;
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
        $this->ensureUserIsLoggedInAndHaveTheRightPermission();
        $page = $this->filterPage();
        $perPage = 10;
        $currenciesList = [];

        $rewardType = $this->filter('reward_type', 'str');
        $direction = $this->filter('direction', 'str');


        $jobFinder = $this->getJobRepo()->findActiveJobs();

        $this->getJobRepo()->applyRewardsFilter($rewardType, $jobFinder);

        $this->getJobRepo()->applyOrderByFilter($jobFinder, $direction);


        $totalJobs = $jobFinder->total();
        if(JobSystemHelper::ensureDbCreditAddonInstalled()){
            $currenciesList = $this->getCurrencyRepo()->getCurrencyTitlePairs();
        }

        if(JobSystemHelper::ensureXFCoderWalletAddonInstalled()) {
            $currenciesList = JobSystemHelper::getXfcoderWalletCurrecies(true) + $currenciesList;
        }





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
                'canInlineMod' => $this->canInlineMod(),
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



        $submissionProcess = $this->app->finder(Submission::class)
            ->where('job_id', $job->job_id)
            ->where('user_id', $visitor->user_id)
            ->order('submission_id', 'DESC')
            ->fetchOne();


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

        if ($job->max_completions > 0) {
            $approvedSubmissionsCount = $this->finder('Olakunlevpn\JobSystem:Submission')
                ->where('job_id', $job->job_id)
                ->where('status', 'approved')
                ->total();

            if ($approvedSubmissionsCount >= $job->max_completions) {
                return $this->error(\XF::phrase('olakunlevpn_job_system_max_completions_reached'));
            }

            \XF::logError($approvedSubmissionsCount);

        }






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
            $submission->submission_data = BbCode::renderFromHtml($input['submission_text']);
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

        $user = XF::app()->find('XF:User', $submission->user_id);
        $moderator = $this->em()->findOne(XFUser::class, ['username' => \XF::app()->options()->olakunlevpn_job_system_job_sn_receiver]);

        JobSystemNotificationService::notifyUserJobPending($submission, $user);
        JobSystemNotificationService::notifyModeratoryJobPending($submission, $moderator);

        return $this->redirect($this->buildLink('jobs/view', $job), \XF::phrase('olakunlevpn_job_system_submission_received'));
    }



    public function actionApply(ParameterBag $params)
    {
        $this->ensureUserIsLoggedAndCanApplyForJob();
        $this->assertRegistrationRequired();

        $jobId = $params->job_id;
        $visitor = \XF::visitor();
        $job = $this->assertJobExists($jobId);

        if ($job->Application) {
            return $this->message('You have already applied for this job.');
        }

        if ($job->max_completions > 0) {
            $approvedSubmissionsCount = \XF::finder('Olakunlevpn\JobSystem:Submission')
                ->where('job_id', $job->job_id)
                ->whereOr([
                    ['status', '=', 'approved'],
                    ['status', '=', 'pending']
                ])
                ->total();

            if ($approvedSubmissionsCount >= $job->max_completions) {
                throw new \XF\PrintableException(\XF::phrase('olakunlevpn_job_system_max_completions_reached'));
            }
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
        $this->ensureUserIsLoggedAndCanWithdraw();

        $this->ensureWithdrawalFeatureIsEnabled();


        $currenciesList = [];
        if(JobSystemHelper::ensureDbCreditAddonInstalled()) {
            $currenciesList = $this->getCurrencyRepo()->getCurrencyTitlePairs();
        }

        if(JobSystemHelper::ensureXFCoderWalletAddonInstalled()) {
            $currenciesList = ['xfcoder_wallet_credit' => XF::phrase('xfcoder_wallet_credit')] + $currenciesList;
        }



        $withdrawProfiles = preg_split('/\s/', $this->options()->olakunlevpn_job_system_paymentProfiles, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($currenciesList))
        {
            return $this->error(\XF::phrase('olakunlevpn_job_system_no_currencies'));
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
        $visitor = $this->ensureUserIsLoggedAndCanWithdraw();
        $db = \XF::db();
        $this->ensureWithdrawalFeatureIsEnabled();



        $withdrawProfiles = preg_split('/\s/', $this->options()->olakunlevpn_job_system_paymentProfiles, -1, PREG_SPLIT_NO_EMPTY);

        $input = $this->filter([
            'currency_id'          => 'str',
            'payment_profile'      => 'str',
            'payment_profile_data' => 'str',
            'amount'               => 'uint'
        ]);

        $amount = $input['amount'];
        if (empty($input['payment_profile_data']) || !in_array($input['payment_profile'], $withdrawProfiles))
        {
            return $this->error(\XF::phrase('olakunlevpn_job_system_select_payment_profile'));
        }


        if (strlen($input['payment_profile_data']) < $this->options()->olakunlevpn_job_system_minimum_wallet_lenght) {
            return $this->error(\XF::phrase('olakunlevpn_job_system_min_wallet_length', ['length' => $this->options()->olakunlevpn_job_system_minimum_wallet_lenght]));
        }

        if ($amount < $this->options()->olakunlevpn_job_system_minimum_withdrawal) {
            return $this->error(
                \XF::phrase('olakunlevpn_job_system_min_withdrawal_amount', [
                    'amount' => $this->options()->olakunlevpn_job_system_minimum_withdrawal
                ])
            );
        }


        if ($amount < $this->options()->olakunlevpn_job_system_minimum_withdrawal)
        {
            return $this->error(\XF::phrase('olakunlevpn_job_system_min_withdrawal_amount', ['amount' => $this->options()->olakunlevpn_job_system_minimum_withdrawal]));
        }

        if(JobSystemHelper::ensureDbCreditAddonInstalled() && $input['currency_id'] != 'xfcoder_wallet_credit') {
            $currency = $this->getCurrencyRepo()
                ->finder('DBTech\Credits:Currency')
                ->where('currency_id', $input['currency_id'])
                ->fetchOne();

            $userCreditAmount = $visitor->{$currency->column};

            if ($userCreditAmount < $amount)
            {
                return $this->noPermission(\XF::phrase('olakunlevpn_job_system_enter_valid_amount', [
                    'balance' => number_format($userCreditAmount, 2),
                    'currency' =>  $currency ? $currency->title : null
                ]));
            }



        }else if(JobSystemHelper::ensureXFCoderWalletAddonInstalled()) {
            if ( $amount > $visitor->xfcoder_wallet_credit )
            {
                $requiredAmount = $amount - $visitor->xfcoder_wallet_credit;

                return $this->error(\XF::phrase('olakunlevpn_job_system_xfcoder_wallet_insufficient_funds_please_topup_x', [
                    'walletLink' => $this->buildLink('account/wallet', [], ['amount' => $requiredAmount]),
                    'minTopupAmount' => JobSystemHelper::getDisplayAmount($requiredAmount)

                ]));
            }

            $db->beginTransaction();

            $fromTx = $this->em()->create('XFCoder\Wallet:Transaction');
            $note = 'You initiated a withdrawal of ' . JobSystemHelper::getDisplayAmount($amount).' from your wallet';

            $fromTx->bulkSet([
                'user_id' => $visitor->user_id,
                'type' => 'transfer',
                'amount' => - $amount,
                'other_user_id' => '',
                'note' => $note
            ]);

            $fromTx->save();

            $db->commit();


        }else{
            return $this->error(\XF::phrase('olakunlevpn_job_system_error_processing_payment'));
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
        $this->ensureUserIsLoggedAndCanWithdraw();

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
        $visitor = $this->getUser();

        if ( ! $visitor || ! $visitor->canViewJob()) {
            throw $this->exception($this->noPermission());
        }

        return $visitor;
    }

    /**
     * @return User
     * @throws XF\Mvc\Reply\Exception
     */
    public function ensureUserIsLoggedAndCanWithdraw(): User
    {
        /** @var User $visitor */
        $visitor = $this->getUser();

        if ( ! $this->getUser() || ! $visitor->canInitiateWithdrawal()) {
            throw $this->exception($this->noPermission());
        }

        return $visitor;
    }



    /**
     * @return User
     * @throws XF\Mvc\Reply\Exception
     */
    public function ensureUserIsLoggedAndCanApplyForJob(): User
    {
        $visitor = $this->getUser();

        if ( ! $visitor || ! $visitor->canApplyForJob()) {
            throw $this->exception($this->noPermission());
        }

        return $visitor;
    }




    /**
     * @return User
     */
    public function getUser()
    {
        /** @var User $visitor */
        $visitor = XF::visitor();
        return $visitor;
    }



    protected function assertJobExists($jobId)
    {
        return $this->assertRecordExists('Olakunlevpn\JobSystem:Job', $jobId);
    }

    private function canInlineMod(): bool
    {
        $visitor = $this->getUser();

        return $visitor && $visitor->canInlineMod();

    }


}