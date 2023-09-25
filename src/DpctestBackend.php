<?php

namespace Drupal\dpctest;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Site\Settings;
use Momento\Auth\StringMomentoTokenProvider;
use Momento\Cache\CacheClient;
use Momento\Config\Configurations\Laptop;

class DpctestBackend implements CacheBackendInterface {

    use LoggerChannelTrait;

    protected $bin;
    protected $client;

    public function __construct($bin) {
        $this->getLogger('momento_cache')->debug('Constructing dpctest with bin: ' . $bin);
        $s = Settings::get('momento_cache');
        $authToken = $s['auth_token'];
        $authProvider = new StringMomentoTokenProvider($authToken);
        $this->bin = $bin;
        $this->client = new CacheClient(Laptop::latest(), $authProvider, 30);
        $this->getLogger('momento_cache')->debug('Got client');
    }

    public function get($cid, $allow_invalid = FALSE) {
        $this->getLogger('momento_cache')->debug('In GET');
        $setResp = $this->client->set("cache", "key", "wooohooo");
        if (!$setResp->asSuccess()) {
            throw $setResp->asError()->innerException();
        }
        $getResp = $this->client->get("cache", "key");
        if ($getResp->asHit()) {
            $this->getLogger('momento_cache')->debug("Get response is: " . $getResp->asHit()->valueString());
        } else {
            $this->getLogger('momento_cache')->debug("Unknown get response: " . $getResp);
        }
        return false;
    }

    public function getMultiple(&$cids, $allow_invalid = FALSE) {
        $this->getLogger('momento_cache')->debug('In GET_MULTIPLE');
        return [];
    }

    public function set($cid, $data, $expire = CacheBackendInterface::CACHE_PERMANENT, array $tags = []) {
        $this->getLogger('momento_cache')->debug('In SET');
    }

    public function setMultiple(array $items) {
        $this->getLogger('momento_cache')->debug('In SET_MULTIPLE');
    }

    public function delete($cid) {
        throw new Exception('not implemented');
    }

    public function deleteMultiple(array $cids) {
        $this->getLogger('momento_cache')->debug('In DELETE_MULTIPLE');
    }

    public function deleteAll() {
        throw new \Exception;
    }

    public function invalidate($cid) {
        throw new Exception('not implemented');
    }

    public function invalidateMultiple(array $cids) {
        throw new Exception('not implemented');
    }

    public function invalidateAll() {
        throw new Exception('not implemented');
    }

    public function invalidateTags(array $tags) {
        throw new Exception('not implemented');
    }

    public function removeBin() {
        throw new Exception('not implemented');
    }

    public function garbageCollection() {
        // Momento will invalidate items; That item's memory allocation is then
        // freed up and reused. So nothing needs to be deleted/cleaned up here.
    }
}
