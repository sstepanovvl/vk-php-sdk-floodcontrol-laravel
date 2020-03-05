<?php
/**
 * Created by PhpStorm.
 * User: stepanstepanov
 * Date: 2020-03-04
 * Time: 19:18
 */

namespace SSV\VKAntiFlood\Repositories;

use Illuminate\Support\Facades\Redis;

class VKAntiFloodRepository
{
    /** @var int */
    const MINIMAL_AWAIT_TIME = 550;
    /** @var string  */
    const LOCK_KEY_PREFIX = "VkRequestLock";
    /** @var string  */
    const VK_REQUEST_MININAL_AWAIT_TIME_PREFIX = "VkRequestMinimalAwaitTime";
    /** @var int  */
    const COMPLICATED_METHOD_LOCK_TIMEOUT_SECONDS = 10;

    private $requestLockKey;

    public function __construct()
    {
        $this->requestLockKey = self::LOCK_KEY_PREFIX.':';
    }

    /**
     * Wait until VK available to receive new request
     * @param string $accessToken
     * @param string $methodName
     */
    public function waitForQueue(string $accessToken, string $methodName) {

        $this->requestLockKey .= $accessToken;

        if ($this->requestIsComplicated($methodName)) {
            do {
                if ($this->canSendComplicatedRequest()) {
                    break;
                } else {
                    usleep(rand(50000,150000));
                }
            } while (true);
        }

        do {
            if ($this->canSendDefaultRequest()) {
                break;
            } else {
                usleep(rand(50000,150000));
            }
        } while (true);
    }

    private function requestIsComplicated(string $methodName) : bool {
        switch ($methodName) {
            case 'ads.updateAds':
            case 'ads.createAds':
            case 'ads.getTargetingStats':
            case 'ads.importTargetContacts':
            case 'ads.getPostsReach':
                return true;
            default:
                return false;
        }
    }


    /**
     * Will release locks
     */
    public function requestCompleted() {
        Redis::connection()->del($this->requestLockKey);
    }

    private function canSendComplicatedRequest() {

        $key = $this->requestLockKey.':Complicated';

        $this->fixKeyPersistence($key);

        if (Redis::connection()->set($key, 1, 'EX', self::COMPLICATED_METHOD_LOCK_TIMEOUT_SECONDS, 'NX')) {
            return true;
        } else {
            if (Redis::connection()->get($key) < 5) {
                Redis::connection()->incr($key);
            } else {
                $estimatedMillisecondsToWait = Redis::connection()->pttl($key);
                usleep($estimatedMillisecondsToWait * 1000);
            }
            return true;
        }
    }

    private function canSendDefaultRequest() {
        $this->fixKeyPersistence();

        return Redis::connection()->set($this->requestLockKey, 1, 'PX', env('VK_REQUEST_LOCK_TIMEOUT', self::MINIMAL_AWAIT_TIME), 'NX');
    }

    /**
     * Sometimes key ttl has -1 value, lets fix it
     * @param string $key
     */
    private function fixKeyPersistence($key = ''): void
    {
        if (!$key) {
            $key = $this->requestLockKey;
        }
        $currentKeyTtl = Redis::connection()->ttl($key) ;
        if ($currentKeyTtl < 0) {
            Redis::connection()->del($key);
        }
    }

}