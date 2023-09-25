<?php

namespace Drupal\dpctest;

use Drupal\Core\Cache\CacheBackendInterface;
use Momento\Cache\Errors\NotImplementedException;
use Drupal\Core\Logger\LoggerChannelTrait;

class DpctestBackend extends CacheBackendInterface {

    use LoggerChannelTrait;

    protected $bin;
    protected $settings = [];

    public function __construct($bin, $settings) {
        $this->bin = $bin;
        $this->settings = $settings;
    }

    public function get($cid, $allow_invalid = FALSE) {
        $this->getLogger('momento_cache')->debug('In GET');
        throw new NotImplementedException();
    }

    public function getMultiple(&$cids, $allow_invalid = FALSE) {
        throw new NotImplementedException();
    }

    public function set($cid, $data, $expire = CacheBackendInterface::CACHE_PERMANENT, array $tags = []) {
        throw new NotImplementedException();
    }

    public function setMultiple(array $items) {
        throw new NotImplementedException();
    }

    public function delete($cid) {
        throw new NotImplementedException();
    }

    public function deleteMultiple(array $cids) {
        throw new NotImplementedException();
    }

    public function deleteAll() {
        throw new NotImplementedException();
    }

    public function invalidate($cid) {
        throw new NotImplementedException();
    }

    public function invalidateMultiple(array $cids) {
        throw new NotImplementedException();
    }

    public function invalidateAll() {
        throw new NotImplementedException();
    }

    public function invalidateTags(array $tags) {
        throw new NotImplementedException();
    }

    public function removeBin() {
        throw new NotImplementedException();
    }

    public function garbageCollection() {
        // Momento will invalidate items; That item's memory allocation is then
        // freed up and reused. So nothing needs to be deleted/cleaned up here.
    }
}
