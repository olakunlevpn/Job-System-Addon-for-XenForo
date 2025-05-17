<?php

namespace Olakunlevpn\JobSystem\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class Application extends Repository
{
    /**
     * Find applications by user ID and status.
     *
     * @param int $userId
     * @param string|null $status Optional status filter.
     * @return Finder
     */
    public function findApplicationsByUser($userId, $status = null)
    {
        $finder = $this->finder('Olakunlevpn\JobSystem:Application')
            ->where('user_id', $userId);

        if ($status) {
            $finder->where('status', $status);
        }

        return $finder;
    }

    /**
     * Find applications for a specific job.
     *
     * @param int $jobId
     * @return Finder
     */
    public function findApplicationsByJob($jobId)
    {
        return $this->finder('Olakunlevpn\JobSystem:Application')
            ->where('job_id', $jobId);
    }

    /**
     * Find applications with a specified status.
     *
     * @param string $status
     * @return Finder
     */
    public function findApplicationsByStatus($status)
    {
        return $this->finder('Olakunlevpn\JobSystem:Application')
            ->where('status', $status);
    }

    /**
     * Approve an application.
     *
     * @param \Olakunlevpn\JobSystem\Entity\Application $application
     * @return bool
     */
    public function approveApplication($application)
    {
        $job = $application->Job;

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

        $application->status = 'approved';
        return $application->save();
    }

    /**
     * Reject an application.
     *
     * @param \Olakunlevpn\JobSystem\Entity\Application $application
     * @param string $comment Optional admin comment for rejection.
     * @return bool
     */
    public function rejectApplication($application, $comment = '')
    {
        $application->status = 'rejected';
        $application->admin_comment = $comment;
        return $application->save();
    }

}