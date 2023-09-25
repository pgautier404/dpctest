<?php

namespace Drupal\dpctest;

use Drupal\Core\Cache\CacheBackendInterface;
use Momento\Cache\Errors\NotImplementedException;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Site\Settings;

class DpctestBackend implements CacheBackendInterface {

    use LoggerChannelTrait;

    protected $bin;

    public function __construct($bin) {
        $this->getLogger('momento_cache')->debug('Constructing dpctest with bin: ' . $bin);
        $this->bin = $bin;
    }

    public function get($cid, $allow_invalid = FALSE) {
//        $this->getLogger('momento_cache')->debug('In GET with random setting: ' . Settings::get('config_sync_directory'));
        $s = Settings::get('momento_cache');
        $this->getLogger('momento_cache')->debug('In GET with: ' . $s['auth_token']);
        return false;
    }

    public function getMultiple(&$cids, $allow_invalid = FALSE) {
        $this->getLogger('momento_cache')->debug('In GET_MULTIPLE');
        return [];
    }

    public function set($cid, $data, $expire = CacheBackendInterface::CACHE_PERMANENT, array $tags = []) {
        $token = Settings::get('momento_cache.auth_token');
        $this->getLogger('momento_cache')->debug('In SET with: ' . $token);
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
