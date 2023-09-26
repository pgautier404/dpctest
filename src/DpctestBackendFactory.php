<?php

namespace Drupal\dpctest;

use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Site\Settings;
use Momento\Auth\StringMomentoTokenProvider;
use Momento\Cache\CacheClient;
use Momento\Config\Configurations\Laptop;

class DpctestBackendFactory implements CacheFactoryInterface {

    use LoggerChannelTrait;

    private $client;

    public function get($bin)
    {
        if (!$this->client) {
            $this->getLogger('momento_cache')->info("Constructing Momento cache client");
            $settings = Settings::get('momento_cache');
            $authToken = $settings['auth_token'];
            $authProvider = new StringMomentoTokenProvider($authToken);
            $this->client = new CacheClient(Laptop::latest(), $authProvider, 30);
        } else {
            $this->getLogger('momento_cache')->debug("Reusing existing Momento cache client");
        }
        return new DpctestBackend($bin, $this->client);
    }
}
