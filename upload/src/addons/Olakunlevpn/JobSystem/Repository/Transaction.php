<?php

namespace Olakunlevpn\JobSystem\Repository;

use XFCoder\Wallet\Repository\Transaction as WalletTransactionRepository;


class Transaction extends WalletTransactionRepository
{
    public function getTransactionTypes(): array
    {
        return array_merge(parent::getTransactionTypes(), [
            'job_reward'
        ]);
    }

}