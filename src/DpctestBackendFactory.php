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
    private $caches = [];
    private $cacheListGoodForSeconds = 3;
    private $cacheListTimespamp;

    private function populateCacheList($bin) {
        $this->getLogger('momento_cache_init')->debug("Listing caches at " . time() . " for bin $bin");
        $this->cacheListTimespamp = time();
        $listResponse = $this->client->listCaches();
        if ($listResponse->asSuccess()) {
            foreach ($listResponse->asSuccess()->caches() as $cache) {
                $this->caches[] = $cache->name();
            }
        }
    }

    public function get($bin)
    {
        if (!$this->client) {
            $this->getLogger('momento_cache_init')->info("Constructing client for $bin at " . time());
            $settings = Settings::get('momento_cache');
            $authToken = $settings['auth_token'];
            $authProvider = new StringMomentoTokenProvider($authToken);
            $this->client = new CacheClient(Laptop::latest(), $authProvider, 30);
        } else {
            $this->getLogger('momento_cache_init')->debug("Reusing client for $bin at " . time());
        }

        if (
            ! $this->caches
            || ($this->cacheListTimespamp && time() - $this->cacheListTimespamp > $this->cacheListGoodForSeconds)
        ) {
            $this->populateCacheList($bin);
        }

        return new DpctestBackend($bin, $this->client, !in_array($bin, $this->caches));
    }
}
