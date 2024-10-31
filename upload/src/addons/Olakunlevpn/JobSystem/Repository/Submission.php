<?php

namespace Olakunlevpn\JobSystem\Repository;

use XF\Mvc\Entity\Repository;

class Submission extends Repository
{
    /**
     * Find all submissions for a given job.
     *
     * @param int $jobId
     * @return \XF\Mvc\Entity\Finder
     */
    public function findSubmissionsByJobId($jobId)
    {
        return $this->finder('Olakunlevpn\JobSystem:Submission')
            ->where('job_id', $jobId)
            ->order('submitted_date', 'DESC');
    }


    /**
     * Find all jobs that are currently active.
     *
     * @return \XF\Mvc\Entity\Finder
     */
    public function findApprovedJobs()
    {
        return $this->finder('Olakunlevpn\JobSystem:Submission')
            ->where('status', 'approved')
            ->order('submitted_date', 'DESC');
    }

    /**
     * Find a submission by its ID.
     *
     * @param int $submissionId
     * @return \Olakunlevpn\JobSystem\Entity\Submission|null
     */
    public function findSubmissionById($submissionId)
    {
        return $this->finder('Olakunlevpn\JobSystem:Submission')
            ->where('submission_id', $submissionId)
            ->fetchOne();
    }
}
