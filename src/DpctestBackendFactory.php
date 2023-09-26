<?php

namespace Drupal\dpctest;

use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Logger\LoggerChannelTrait;

class DpctestBackendFactory implements CacheFactoryInterface {

    use LoggerChannelTrait;

//    public function __construct($settings)
//    {
//
//    }

    public function get($bin)
    {
        $this->getLogger('momento_cache')->debug("hi from the factory floor!");
        return new DpctestBackend($bin);
    }
}
