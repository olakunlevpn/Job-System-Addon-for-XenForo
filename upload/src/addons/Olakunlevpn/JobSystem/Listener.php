<?php

namespace Olakunlevpn\JobSystem;


class Listener
{
    public static function appPubSetup(\XF\App $app)
    {
        self::addonSetup($app);

    }

    public static function appAdminSetup(\XF\App $app)
    {
        self::addonSetup($app);
    }

    /**
     * @param  \XF\App  $app
     * @return void
     */
    public static function addonSetup(\XF\App $app): void
    {
        $container = $app->container();
        $addOns = \XF::app()->container('addon.cache');

        $container['olakunlevpn_job_system_addonExistsDragonByteCredits'] = isset($addOns['DBTech/Credits']);
        $container['olakunlevpn_job_system_addonExistsXFCoderWallet'] = isset($addOns['XFCoder/Wallet']);
    }


}