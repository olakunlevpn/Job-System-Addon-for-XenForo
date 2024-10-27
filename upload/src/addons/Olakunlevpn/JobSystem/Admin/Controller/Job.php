<?php

namespace Olakunlevpn\JobSystem\Admin\Controller;


use Olakunlevpn\JobSystem;
use XF\Admin\Controller\AbstractController;
use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\EditorPlugin;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\Mvc\Reply\Redirect;
use XF\PrintableException;


class Job extends AbstractController
{
   public function preDispatchController($action, ParameterBag $params)
   {
       $this->setSectionContext('jobs');
   }

   public function actionIndex(ParameterBag $params)
   {

       if ($params['job_id'])
       {
           return $this->rerouteController(__CLASS__, 'job', $params);
       }

       $this->setSectionContext('Jobs_list');

       $page = $this->filterPage();
       $perPage = 20;

       $jobFinder = $this->finder('Olakunlevpn\JobSystem:Job')
           ->setDefaultOrder('created_date', 'DESC')
           ->limitByPage($page, $perPage);

       $viewParams = [
           'jobs' => $jobFinder->fetch(),
           'total' => $jobFinder->total(),
           'page' => $page,
           'perPage' => $perPage
       ];

       return $this->view('Olakunlevpn\JobSystem:JobList', 'olakunlevpn_admin_job_system_list',  $viewParams);
   }



    public function actionAdd()
    {
        $job = $this->em()->create('Olakunlevpn\JobSystem:Job');
        return $this->jobAddEdit($job);
    }

    public function actionEdit(ParameterBag $params)
    {
        $jobId = $params->job_id;
        $job = $this->assertJobExists($jobId);

        return $this->jobAddEdit($job);
    }

    protected function jobAddEdit(\Olakunlevpn\JobSystem\Entity\Job $job)
    {
        $currencies = $this->getDragonByteCurrencies();

        $trophyRepo = \XF::repository('XF:Trophy');
        $trophies = $trophyRepo->findTrophiesForList()
            ->fetch();


        $viewParams = [
            'job' => $job,
             'currencies' => $currencies,
            'trophies' => $trophies
        ];
        return $this->view('Olakunlevpn\JobSystem:Job\Edit', 'olakunlevpn_admin_job_system_add', $viewParams);
    }






    /**
     * @param  ParameterBag  $params
     * @return Redirect
     * @throws PrintableException
     */
    public function actionSave(ParameterBag $params)
    {
        $jobId = $params->job_id;
        if ($jobId) {
            $job = $this->assertJobExists($jobId);
        } else {
            $job = $this->em()->create('Olakunlevpn\JobSystem:Job');
        }

        $input = $this->filter([
            'title' => 'str',
            'description' => 'str',
            'details' => 'str',
            'reward_type' => 'str',
            'max_completions' => 'uint',
            'type' => 'str',
            'has_attachment' => 'bool',
            'active' => 'bool'
        ]);


        if ($input['reward_type'] === 'db_credits') {

            $creditSpecificInput = $this->filter([
                'reward_amount' => 'float',
                'reward_currency' => 'str'
            ]);

            $input = array_merge($input, $creditSpecificInput);

        } else if ($input['reward_type'] === 'trophy') {

            $trophySpecificInput = $this->filter([
                'trophy_id' => 'uint'
            ]);

            $input = array_merge($input, $trophySpecificInput);
        }



        $input['details'] = $this->plugin(EditorPlugin::class)->fromInput('details');



        $job->bulkSet($input);
        $job->save();

        return $this->redirect($this->buildLink('admin:jobs'));
    }


    public function actionDelete(ParameterBag $params): AbstractReply
    {
        // Fetch the job using the job ID from the URL
        $job = $this->assertJobExists($params->job_id);

        // Use the DeletePlugin to handle the deletion
        $plugin = $this->plugin(DeletePlugin::class);
        return $plugin->actionDelete(
            $job,
            $this->buildLink('jobs/delete', $job),
            $this->buildLink('jobs/edit', $job),
            $this->buildLink('jobs'),
            $job->title
        );
    }


    /**
     * @param $jobId
     * @return JobSystem:Job|\XF\Mvc\Entity\Entity
     * @throws Exception
     */
    protected function assertJobExists($jobId)
    {
        return $this->assertRecordExists('Olakunlevpn\JobSystem:Job', $jobId);
    }

    protected function getDragonByteCurrencies()
    {
        $currencyRepo = \XF::repository('DBTech\Credits:Currency');
        return $currencyRepo->getCurrencyOptionsData(false);
    }





}