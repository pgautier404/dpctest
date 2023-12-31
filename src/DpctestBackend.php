<?php

namespace Drupal\dpctest;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Site\Settings;
use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Momento\Requests\CollectionTtl;

class DpctestBackend implements CacheBackendInterface, CacheTagsInvalidatorInterface {

    use LoggerChannelTrait;

    protected $bin;
    protected $client;
    private $MAX_TTL;

    public function __construct($bin, $client, $createCache) {
        $this->MAX_TTL = intdiv(PHP_INT_MAX, 1000);
        $this->client = $client;
        $this->bin = $bin;

        if ($createCache) {
            $createResponse = $this->client->createCache($bin);
            if ($createResponse->asError()) {
                $this->getLogger('momento_cache')->error(
                    "Error creating cache $bin : " . $createResponse->asError()->message()
                );
            } elseif ($createResponse->asSuccess()) {
                $this->getLogger('momento_cache')->info("Created cache $bin");
            }
        }
    }

    public function get($cid, $allow_invalid = FALSE) {
        $this->getLogger('momento_cache')->debug("GET with bin $this->bin, cid " . $cid);
        $cids = [$cid];
        $recs = $this->getMultiple($cids);
        return reset($recs);
    }

    public function getMultiple(&$cids, $allow_invalid = FALSE) {
        $this->getLogger('momento_cache')->debug(
            "GET_MULTIPLE for bin $this->bin, cids: " . implode(', ', $cids)
        );

        $fetched = [];
        foreach (array_chunk($cids, 100) as $cidChunk) {
            $futures = [];
            foreach ($cidChunk as $cid) {
                $futures[$cid] = $this->client->getAsync($this->bin, $cid);
            }

            foreach ($futures as $cid => $future) {
                $getResponse = $future->wait();
                if ($getResponse->asHit()) {
                    $fetched[$cid] = unserialize($getResponse->asHit()->valueString());
                    $this->getLogger('momento_cache')->debug("Successful GET for cid $cid");
                } elseif ($getResponse->asError()) {
                    $this->getLogger('momento_cache')->error("GET error for cid $cid: " . $getResponse->asError()->message());
                }
            }
        }

        return $fetched;
    }

    private function getTagSetName($tag) {
        return "_tagSet:$tag";
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

        $this->getLogger('momento_cache')->debug("SET cid $cid in bin $this->bin with ttl $ttl");
        $setResponse = $this->client->set($this->bin, $cid, serialize($item), $ttl);
        if ($setResponse->asError()) {
            $this->getLogger('momento_cache')->error("SET response error: " . $setResponse->asError()->message());
        }
        foreach ($tags as $tag) {
            $tagSetName = $this->getTagSetName($tag);
            // TODO: either prefix or hash set name so we don't accidentally overwrite keys if there is ever a collision
            $setAddElementResponse = $this->client->setAddElement($this->bin, $tagSetName, $cid, CollectionTtl::of($this->MAX_TTL));
            if ($setAddElementResponse->asError()) {
                $this->getLogger('momento_cache')->error("TAG add error $tag: " . $setAddElementResponse->asError()->message());
            }
        }
    }

    public function setMultiple(array $items) {
        $this->getLogger('momento_cache')->debug("SET_MULTIPLE in bin $this->bin for " . count($items) . " items");

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
        // TODO: remove this cid from tags sets? would require fetching the item and accessing the tags.
        $this->getLogger('momento_cache')->debug("DELETE cid $cid");
        $deleteResponse = $this->client->delete($this->bin, $cid);
        if ($deleteResponse->asError()) {
            $this->getLogger('momento_cache')->error("DELETE response error: " . $deleteResponse->asError()->message());
        } else {
            $this->getLogger('momento_cache')->debug("DELETED $cid");
        }
    }

    public function deleteMultiple(array $cids) {
        $this->getLogger('momento_cache')->debug(
            "DELETE_MULTIPLE in bin $this->bin for cids: " . implode(', ', $cids)
        );
        foreach ($cids as $cid) {
            $this->delete($cid);
        }
    }

    public function deleteAll() {
        $this->getLogger('momento_cache')->debug("DELETE_ALL in bin $this->bin");
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
        $this->getLogger('momento_cache')->debug("INVALIDATE cid $cid for bin $this->bin");
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
        $this->getLogger('momento_cache')->debug("INVALIDATE_TAGS with tags: " . implode(', ', $tags));
        foreach ($tags as $tag) {
            $tagSetName = $this->getTagSetName($tag);
            $this->getLogger('momento_cache')->debug("Processing tag '$tag' in bin $this->bin");
            $setFetchResponse = $this->client->setFetch($this->bin, $tagSetName);
            if ($setFetchResponse->asError()) {
                $this->getLogger('momento_cache')->error(
                    "Error fetching TAG $tag from bin $this->bin: " . $setFetchResponse->asError()->message()
                );
            } elseif ($setFetchResponse->asHit()) {
                $cids = $setFetchResponse->asHit()->valuesArray();
                $this->getLogger('momento_cache')->debug(
                    "Deleting $tag from bin $this->bin: " . implode(', ', $cids)
                );
                $this->deleteMultiple($cids);
            } elseif ($setFetchResponse->asMiss()) {
                $this->getLogger('momento_cache')->debug("No cids found for tag $tag in bin $this->bin");
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
