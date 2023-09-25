<?php

namespace Drupal\dpctest;

use Drupal\Core\Cache\CacheBackendInterface;
use Momento\Cache\Errors\NotImplementedException;
use Drupal\Core\Logger\LoggerChannelTrait;

class DpctestBackend implements CacheBackendInterface {

    use LoggerChannelTrait;

    protected $bin;

    public function __construct($bin) {
        $this->getLogger('momento_cache')->debug('Constructing dpctest');
        $this->bin = $bin;
    }

    public function get($cid, $allow_invalid = FALSE) {
        $this->getLogger('momento_cache')->debug('In GET');
        return false;
    }

    public function getMultiple(&$cids, $allow_invalid = FALSE) {
        $this->getLogger('momento_cache')->error('In GET_MULTIPLE');
        return [];
    }

    public function set($cid, $data, $expire = CacheBackendInterface::CACHE_PERMANENT, array $tags = []) {
        $this->getLogger('momento_cache')->debug('In SET');
    }

    public function setMultiple(array $items) {
        $this->getLogger('momento_cache')->debug('In SET_MULTIPLE');
    }

    public function delete($cid) {
        throw new NotImplementedException();
    }

    public function deleteMultiple(array $cids) {
        $this->getLogger('momento_cache')->debug('In DELETE_MULTIPLE');
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
