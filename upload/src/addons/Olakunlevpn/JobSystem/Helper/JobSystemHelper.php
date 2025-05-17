<?php

namespace Olakunlevpn\JobSystem\Helper;

use XF\Mvc\Entity\Entity;
use XF;

class JobSystemHelper
{
    /**
     * Prepare job details to be rendered as raw HTML content.
     * @param Entity|\Olakunlevpn\JobSystem\Entity\Job $job
     */
    public static function prepareJobDetails(Entity $job)
    {
        return XF::app()->bbCode()->render($job->details, 'html', 'job', $job);
    }


    public static function processPageContent(string $content): string
    {
        return str_replace('{$xf.options.boardTitle}', XF::options()->boardTitle, $content);
    }


    public static function ensureDbCreditAddonInstalled()
    {
        $addOns = \XF::app()->container('addon.cache');
        return array_key_exists('DBTech/Credits', $addOns);

    }


    public static function ensureXFCoderWalletAddonInstalled()
    {
        $addOns = \XF::app()->container('addon.cache');
        return array_key_exists('XFCoder/Wallet', $addOns);

    }

    public static function getXfcoderWalletCurrecies($key= false)
    {
        if($key){
           return ['xfcoder_wallet_credit' => \XF::phrase('olakunlevpn_jobsystem_xfcoder_wallet_credit')];
        }
        $xfcoderWallet = [
            'xfcoder_wallet_credit' => [
                'value' => 'xfcoder_wallet_credit',
                'label' => \XF::phrase('olakunlevpn_jobsystem_xfcoder_wallet_credit')
            ]
        ];
        return $xfcoderWallet;
    }


    public static function getDisplayAmount($amount, $plusPrefix = false)
    {
        $currencyCode = \XF::options()->XFCoderWallet_currencyCode;
        $currencyData = \XF::app()->data('XF:Currency');
        $currencySymbol = $currencyData->getCurrencySymbol($currencyCode);

        $symbol = !preg_match('/[a-z]+/i', $currencySymbol) ? $currencySymbol : null;
        $prefix = $amount < 0 ? '-' : ( $plusPrefix && $amount ? '+' : '' );
        $precision = $amount != (int) $amount ? 2 : 0;

        return
            ( $symbol ? $prefix.$symbol : $currencyCode.' '.$prefix )
            .\XF::language()->numberFormat(abs($amount), $precision);
    }



}