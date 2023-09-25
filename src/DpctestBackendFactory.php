<?php

namespace Drupal\dpctest;

use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
//use Drupal\Core\Site\Settings;

class DpctestBackendFactory implements CacheFactoryInterface {

    use LoggerChannelTrait;

//    protected $settings = [];

//    public function __construct(Settings $settings)
//    {
//        $this->getLogger('momento_cache')->debug(
//          'Got settings: @settings',
//          ['@settings' => $settings]
//        );
//        $this->settings = $settings;
//    }

    public function get($bin)
    {
        $this->getLogger('momento_cache')->debug("hi from the factory floor!");
        return new DpctestBackend($bin);
    }
}
