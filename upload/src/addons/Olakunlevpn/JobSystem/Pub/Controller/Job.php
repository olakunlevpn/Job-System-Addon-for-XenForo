<?php

namespace Olakunlevpn\JobSystem\Pub\Controller;

use XF;
use XF\Mvc\Entity\Repository;
use XF\Mvc\ParameterBag;
use DBTech\Credits\XF\Entity\User;
use XF\ControllerPlugin\Attachment;
use DBTech\Credits\Repository\Currency;
use XF\Repository\AttachmentRepository;
use XF\Pub\Controller\AbstractController;
use XF\Api\ControllerPlugin\AttachmentPlugin;
use Olakunlevpn\JobSystem\Entity\WithdrawRequest;



class Job extends AbstractController
{

    protected function preDispatchController($action, ParameterBag $params)
    {
        /** @var User $visitor */
        $visitor = XF::visitor();

        if (!$visitor)
        {
            throw $this->exception($this->noPermission());
        }
    }


    public function actionIndex()
    {

        $jobs = $this->getJobRepo()->findActiveJobs()->fetch();


        return $this->view(
            'Olakunlevpn\JobSystem:Job\Index',
            'olakunlevpn_job_system_welcome',
            ['jobs' => $jobs,
                'page_title' => $this->options()->olakunlevpn_job_system_welcome_page_title,
                'page_content' => $this->options()->olakunlevpn_job_system_welcome_page_content,
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
            return $this->message(' The requested job could not be found.');
        }


        $submission = $this->em()->create('Olakunlevpn\JobSystem:Submission');
        $submission->job_id = $job->job_id;
        $submission->user_id = \XF::visitor()->user_id;

        $submissionProcess = $this->em()->findOne('Olakunlevpn\JobSystem:Submission', [
            'job_id' => $jobId,
            'user_id' => \XF::visitor()->user_id
        ]);


        $attachmentRepo = $this->repository('XF:Attachment');
        $attachmentData = $attachmentRepo->getEditorData('job_submission_tasks', $submission);



        $job->details = \XF::app()->bbCode()->render($job->details, 'html', 'job', $job);

        $viewParams = [
            'job' => $job,
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
        $app = \XF::app();

        $jobId = $params->job_id;
        $job = $this->assertJobExists($jobId);
        \XF::session()->set('flashMessage', 'Your submission has been received!');

        $input = $this->filter([
            'submission_text' => 'str',
            'submission_url' => 'str',
            'attachment_hash' => 'str',
        ]);


        $submission = $app->em()->create('Olakunlevpn\JobSystem:Submission');
        $submission->job_id = $jobId;
        $submission->user_id = \XF::visitor()->user_id;


        if ($job->type == 'text') {
            if (empty($input['submission_text'])) {
                return $this->error('Please enter your response for the job.');
            }
            $submission->submission_data = $input['submission_text'];
        } else {
            if (empty($input['submission_url'])) {
                return $this->error('Please enter the URL for your submission.');
            }

            if (!filter_var($input['submission_url'], FILTER_VALIDATE_URL)) {
                return $this->error('Please enter a valid URL.');
            }


            $submission->submission_data = $input['submission_url'];
        }


        $submission->submitted_date = \XF::$time;
        $submission->attachment_hash = $input['attachment_hash'];
        $submission->save();


        $inserter = $this->service(\XF\Service\Attachment\PreparerService::class);
        $associated = $inserter->associateAttachmentsWithContent($input['attachment_hash'], 'job_submission_tasks', $submission->submission_id);
        if (!$associated)
        {
            return $this->error('We are unable to upload your images, please try again later.');
        }

        $this->notifyUserJobPending($submission);


        return $this->redirect($this->buildLink('jobs/view', $job), 'Your submission has been received!');
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


    public function actionCompleted()
    {

        $page = $this->filterPage();
        $perPage = 10;

        $jobFinder = $this->getJobRepo()->findApprovedJobs();
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

        $page = $this->filterPage();
        $perPage = 10;

        $jobFinder = $this->getJobRepo()->findPendingJobs();
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

        /** @var User $visitor */
        $visitor = XF::visitor();

        if (!$visitor || !$visitor->canViewDbtechCredits())
        {
            throw $this->exception($this->noPermission());
        }

        $withdrawProfiles = preg_split('/\s/', $this->options()->olakunlevpn_job_system_paymentProfiles, -1, PREG_SPLIT_NO_EMPTY);

        $input = $this->filter([
            'currency_id'          => 'uint',
            'payment_profile'      => 'str',
            'payment_profile_data' => 'str',
            'amount'               => 'num'
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


        if (strlen($input['payment_profile_data']) < $this->options()->olakunlevpn_job_system_minimumWalletlenght ) {
            return $this->error("The wallet address must be at least {$this->options()->olakunlevpn_job_system_minimumWalletlenght} characters long.");
        }




        $userCreditAmount = $visitor->get($currency->column);
        if ($userCreditAmount < $input['amount'])
        {
            return $this->noPermission('Please enter valid amount');
        }

        if ($input['amount'] < $this->options()->olakunlevpn_job_system_minimumWithdrawal)
        {
            return $this->error("The minimum withdrawal amount is $".$this->options()->olakunlevpn_job_system_minimumWithdrawal);
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
     */
    public function actionWithdrawList(): \XF\Mvc\Reply\View
    {

        $withdrawRequests = $this->finder('Olakunlevpn\JobSystem:WithdrawRequest')->where('user_id', XF::visitor()->user_id)
            ->order('withdraw_request_id', 'desc')
            ->fetch();

        $viewParams = [
            'withdrawRequests' => $withdrawRequests,
            'pageSelected'     => 'withdrawList'
        ];


        return $this->view('Olakunlevpn\JobSystem:Job\Withdraw\List', 'olakunlevpn_job_system_withdrawal_list', $viewParams);


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



    protected function getCurrencyRepo(): Currency
    {
        return $this->repository('DBTech\Credits:Currency');
    }


}