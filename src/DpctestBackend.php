<?php

namespace Drupal\dpctest;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Site\Settings;
use Drupal\Component\Serialization\SerializationInterface;
use Momento\Auth\StringMomentoTokenProvider;
use Momento\Cache\CacheClient;
use Momento\Config\Configurations\Laptop;

class DpctestBackend implements CacheBackendInterface {

    use LoggerChannelTrait;

    protected $bin;
    protected $client;

    public function __construct($bin) {
        $this->getLogger('momento_cache')->notice('Constructing dpctest with bin: ' . $bin);
        $settings = Settings::get('momento_cache');
        $authToken = $settings['auth_token'];
        $authProvider = new StringMomentoTokenProvider($authToken);
        $this->bin = $bin;
        $this->client = new CacheClient(Laptop::latest(), $authProvider, 30);
        $this->getLogger('momento_cache')->notice('Got client');
        $createResponse = $this->client->createCache($bin);
        if ($createResponse->asError()) {
            $this->getLogger('momento_cache')->error(
                'Error creating cache ' . $bin . ': ' . $createResponse->asError()->message()
            );
        } elseif ($createResponse->asSuccess()) {
            $this->getLogger('momento_cache')->notice('Created cache: ' . $bin);
        }
    }

    public function get($cid, $allow_invalid = FALSE) {
        $this->getLogger('momento_cache')->debug('In GET with bin: ' . $this->bin);

        // TODO: pass off to getMultiple
        $cids = [$cid];
        $recs = $this->getMultiple($cids);
        return reset($recs);
    }

    public function getMultiple(&$cids, $allow_invalid = FALSE) {
        $this->getLogger('momento_cache')->debug('In GET_MULTIPLE for bin: ' . $this->bin);
        $fetched = [];

        foreach ($cids as $cid) {
            $getResponse = $this->client->get($this->bin, $cid);
            if ($getResponse->asHit()) {
                $fetched[$cid] = unserialize($getResponse->asHit()->valueString());
                $this->getLogger('momento_cache')->debug(
                    "Get response (JSON encoded) for cid: " . $cid . " is: " . json_encode($fetched[$cid])
                );
            } elseif ($getResponse->asError()) {
                $this->getLogger('momento_cache')->debug("Get response error: " . $getResponse->asError()->message());
            }
        }
        return $fetched;
    }

    public function set($cid, $data, $expire = CacheBackendInterface::CACHE_PERMANENT, array $tags = []) {
        $this->getLogger('momento_cache')->debug(
            'In SET with bin: ' . $this->bin . ', data: ' . json_encode($data)
        );
        $item = new \stdClass();
        $item->cid = $cid;
        $item->data = $data;
        $item->created = round(microtime(TRUE), 3);

        $ttl = intdiv(PHP_INT_MAX, 1000);
        if ($expire != CacheBackendInterface::CACHE_PERMANENT) {
            $ttl = $expire - \Drupal::time()->getRequestTime();
        }
        $this->getLogger('momento_cache')->debug('SET TTL: ' . $ttl);
        $item->expire = $expire;
        $item->tags = $tags;
        $setResponse = $this->client->set($this->bin, $cid, serialize($item));
    }

    public function setMultiple(array $items) {
        $this->getLogger('momento_cache')->debug('In SET_MULTIPLE');
        foreach ($items as $cid => $item) {
            $this->set(
                $cid,
                $item['data'],
                $item['expire'] ?? CacheBackendInterface::CACHE_PERMANENT,
                $item['tags'] ?? []
            );
        }
    }

    public function delete($cid) {
        $this->getLogger('momento_cache')->debug('In DELETE');
    }

    public function deleteMultiple(array $cids) {
        $this->getLogger('momento_cache')->debug('In DELETE_MULTIPLE');
        throw new \Exception('not implemented');
    }

    public function deleteAll() {
        $this->getLogger('momento_cache')->debug('In DELETE_ALL');
        throw new \Exception('not implemented');
    }

    public function invalidate($cid) {
        $this->getLogger('momento_cache')->debug('In INVALIDATE');
        throw new \Exception('not implemented');
    }

    public function invalidateMultiple(array $cids) {
        $this->getLogger('momento_cache')->debug('In INVALIDATE_MULTIPLE');
        throw new \Exception('not implemented');
    }

    public function invalidateAll() {
        $this->getLogger('momento_cache')->debug('In INVALIDATE_ALL');
        throw new \Exception('not implemented');
    }

    public function invalidateTags(array $tags) {
        $this->getLogger('momento_cache')->debug('In INVALIDATE_TAGS');
        throw new \Exception('not implemented');
    }

    public function removeBin() {
        $this->getLogger('momento_cache')->debug('In REMOVE_BIN');
        throw new \Exception('not implemented');
    }

    public function garbageCollection() {
        // Momento will invalidate items; That item's memory allocation is then
        // freed up and reused. So nothing needs to be deleted/cleaned up here.
    }
}
