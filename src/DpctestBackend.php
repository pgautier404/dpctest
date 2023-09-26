<?php

namespace Drupal\dpctest;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Site\Settings;
use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Momento\Auth\StringMomentoTokenProvider;
use Momento\Cache\CacheClient;
use Momento\Config\Configurations\Laptop;
use Momento\Requests\CollectionTtl;

class DpctestBackend implements CacheBackendInterface, CacheTagsInvalidatorInterface {

    use LoggerChannelTrait;

    protected $bin;
    protected $client;
    private $MAX_TTL;

    public function __construct($bin, $client) {
        $this->MAX_TTL = intdiv(PHP_INT_MAX, 1000);
        $this->client = $client;
        $this->bin = $bin;
        $createResponse = $this->client->createCache($bin);
        if ($createResponse->asError()) {
            $this->getLogger('momento_cache')->error(
                "Error creating cache $bin : " . $createResponse->asError()->message()
            );
        } elseif ($createResponse->asSuccess()) {
            $this->getLogger('momento_cache')->info("Created cache: $bin");
        }
    }

    public function get($cid, $allow_invalid = FALSE) {
        $this->getLogger('momento_cache')->debug("GET with bin " . $this->bin . ", cid " . $cid);
        $cids = [$cid];
        $recs = $this->getMultiple($cids);
        return reset($recs);
    }

    public function getMultiple(&$cids, $allow_invalid = FALSE) {
        $this->getLogger('momento_cache')->debug(
            "GET_MULTIPLE for bin " . $this->bin . ", cids: " . implode(', ', $cids)
        );
        $fetched = [];

        foreach ($cids as $cid) {
            $getResponse = $this->client->get($this->bin, $cid);
            if ($getResponse->asHit()) {
                $fetched[$cid] = unserialize($getResponse->asHit()->valueString());
                $this->getLogger('momento_cache')->debug("Successful GET for cid $cid");
            } elseif ($getResponse->asError()) {
                $this->getLogger('momento_cache')->error("GET error for cid $cid: " . $getResponse->asError()->message());
            }
        }
        return $fetched;
    }

    public function set($cid, $data, $expire = CacheBackendInterface::CACHE_PERMANENT, array $tags = []) {
        $ttl = $this->MAX_TTL;
        $item = new \stdClass();
        $item->cid = $cid;
        $item->data = $data;
        $item->created = round(microtime(TRUE), 3);
        if ($expire != CacheBackendInterface::CACHE_PERMANENT) {
            $ttl = $expire - \Drupal::time()->getRequestTime();
        }
        $item->expire = $expire;
        $item->tags = $tags;
        $this->getLogger('momento_cache')->debug("SET cid $cid in bin " . $this->bin . " with ttl $ttl");
        $setResponse = $this->client->set($this->bin, $cid, serialize($item), $ttl);
        if ($setResponse->asError()) {
            $this->getLogger('momento_cache')->error("SET response error: " . $setResponse->asError()->message());
        }
        foreach ($tags as $tag) {
            // TODO: either prefix or hash set name so we don't accidentally overwrite keys if there is ever a collision
            $setAddElementResponse = $this->client->setAddElement($this->bin, $tag, $cid, CollectionTtl::of($this->MAX_TTL));
            if ($setAddElementResponse->asError()) {
                $this->getLogger('momento_cache')->error("TAG add error $tag: " . $setAddElementResponse->asError()->message());
            }
        }
    }

    public function setMultiple(array $items) {
        $this->getLogger('momento_cache')->debug('SET_MULTIPLE in bin ' . $this->bin . ' for ' . count($items) . ' items');
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
        $deleteResponse = $this->client->delete($this->bin, $cid);
        if ($deleteResponse->asError()) {
            $this->getLogger('momento_cache')->error("DELETE response error: " . $deleteResponse->asError()->message());
        } else {
            $this->getLogger('momento_cache')->error("DELETED $cid");
        }
    }

    public function deleteMultiple(array $cids) {
        $this->getLogger('momento_cache')->debug('In DELETE_MULTIPLE');
        foreach ($cids as $cid) {
            $this->delete($cid);
        }
    }

    public function deleteAll() {
        $this->getLogger('momento_cache')->debug('In DELETE_ALL');
        // TODO: we don't have flushCache in the PHP SDK yet
        $deleteResponse = $this->client->deleteCache($this->bin);
        if ($deleteResponse->asError()) {
            $this->getLogger('momento_cache')->error("DELETE_CACHE response error: " . $deleteResponse->asError()->message());
            return;
        }
        $createResponse = $this->client->createCache($this->bin);
        if ($createResponse->asError()) {
            $this->getLogger('momento_cache')->error("CREATE_CACHE response error: " . $createResponse->asError()->message());
        }
    }

    public function invalidate($cid) {
        $this->getLogger('momento_cache')->debug('In INVALIDATE');
        $this->delete($cid);
    }

    public function invalidateMultiple(array $cids) {
        $this->getLogger('momento_cache')->debug('In INVALIDATE_MULTIPLE');
        $this->deleteMultiple($cids);
    }

    public function invalidateAll() {
        $this->getLogger('momento_cache')->debug('In INVALIDATE_ALL');
        $this->deleteAll();
    }

    public function invalidateTags(array $tags) {
        $this->getLogger('momento_cache')->error('In INVALIDATE_TAGS with tags: ' . implode(', ', $tags));
        foreach ($tags as $tag) {
            $this->getLogger('momento_cache')->error("Considering tag '$tag' in bin " . $this->bin);
            $setFetchResponse = $this->client->setFetch($this->bin, $tag);
            if ($setFetchResponse->asError()) {
                $this->getLogger('momento_cache')->error(
                    "Error fetching TAG $tag from bin " . $this->bin . ": " . $setFetchResponse->asError()->message()
                );
            } elseif ($setFetchResponse->asHit()) {
                $cids = $setFetchResponse->asHit()->valuesArray();
                $this->getLogger('momento_cache')->error(
                    "Deleting $tag from bin " . $this->bin . ": " . implode(', ', $cids)
                );
                $this->deleteMultiple($cids);
            } elseif ($setFetchResponse->asMiss()) {
                $this->getLogger('momento_cache')->error("No cids found for tag $tag in bin " . $this->bin);
            }
        }
    }

    public function removeBin() {
        $this->getLogger('momento_cache')->debug('In REMOVE_BIN');
        $deleteResponse = $this->client->deleteCache($this->bin);
        if ($deleteResponse->asError()) {
            $this->getLogger('momento_cache')->error("DELETE_CACHE response error: " . $deleteResponse->asError()->message());
        }
    }

    public function garbageCollection() {
        // Momento will invalidate items; That item's memory allocation is then
        // freed up and reused. So nothing needs to be deleted/cleaned up here.
    }
}
