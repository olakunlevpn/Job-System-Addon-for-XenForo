<?php

namespace Olakunlevpn\JobSystem\Repository;

use XF\Mvc\Entity\Repository;

class Job extends Repository
{
    /**
     * Find all jobs that are currently active.
     *
     * @return \XF\Mvc\Entity\Finder
     */
    public function findActiveJobs()
    {
        return $this->finder('Olakunlevpn\JobSystem:Job')
            ->where('active', true);
    }


    /**
     * Find all jobs that are currently Approved.
     *
     * @return \XF\Mvc\Entity\Finder
     */
    public function findApprovedJobs()
    {
        return $this->finder('Olakunlevpn\JobSystem:Submission')
            ->with('Job')
            ->where('status', 'approved')
            ->where('Job.active', true)
            ->order('Job.created_date', 'DESC');
    }


    /**
     * Find all jobs that are currently pending.
     *
     * @return \XF\Mvc\Entity\Finder
     */
    public function findPendingJobs()
    {
        return $this->finder('Olakunlevpn\JobSystem:Submission')
            ->with('Job')
            ->where('status', ['pending', 'rejected'])
            ->where('Job.active', true)
            ->order('Job.created_date', 'DESC');
    }


    /**
     * Find a job by its ID.
     *
     * @param int $jobId
     * @return \Olakunlevpn\JobSystem\Entity\Job|null
     */
    public function findJobById($jobId)
    {
        return $this->finder('Olakunlevpn\JobSystem:Job')
            ->where('job_id', $jobId)
            ->fetchOne();
    }

    /**
     * Retrieve all jobs.
     *
     * @return \XF\Mvc\Entity\Finder
     */
    public function findAllJobs()
    {
        return $this->finder('Olakunlevpn\JobSystem:Job')
            ->order('created_date', 'DESC');
    }


    /**
     * Find approved submissions for a specific user.
     *
     * @param int $userId
     * @return \XF\Mvc\Entity\Finder
     */
    public function findUserApprovedSubmissions($userId)
    {
        return $this->finder('Olakunlevpn\JobSystem:Submission')
            ->with('Job')
            ->where('status', 'approved')
            ->where('user_id', $userId)
            ->order('Job.created_date', 'DESC');
    }


    /**
     * Find pending submissions for a specific user.
     *
     * @param int $userId
     * @return \XF\Mvc\Entity\Finder
     */
    public function findUserPendingSubmissions($userId)
    {
        return $this->finder('Olakunlevpn\JobSystem:Submission')
            ->with('Job')
            ->where('status', 'pending')
            ->where('user_id', $userId)
            ->order('Job.created_date', 'DESC');
    }


    /**
     * @param  mixed  $rewardType
     * @param $jobFinder
     * @return void
     */
    public function applyRewardsFilter(mixed $rewardType, $jobFinder): void
    {
        if ($rewardType && $rewardType !== 'any') {
            if ($rewardType === 'trophy') {
                $jobFinder->where('reward_type', 'trophy');
            } else {
                $jobFinder->where('reward_type', 'db_credits')->where('reward_currency', $rewardType);
            }
        }
    }

    /**
     * @param $jobFinder
     * @param  mixed  $direction
     * @return void
     */
    public function applyOrderByFilter($jobFinder, mixed $direction): void
    {
        $jobFinder->order('created_date', $direction === 'desc' ? 'DESC' : 'ASC');
    }





}
