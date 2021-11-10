<?php



namespace RateLimit;

use RateLimit\Exception\LimitExceeded;
use Redis;
use function ceil;
use function max;
use function time;

final class RedisRateLimiter extends ConfigurableRateLimiter implements RateLimiter, SilentRateLimiter
{
    private $redis;
    private $keyPrefix;

    public function __construct(Rate $rate, Redis $redis, $keyPrefix = '')
    {
        parent::__construct($rate);
        $this->redis = $redis;
        $this->keyPrefix = $keyPrefix;
    }

    public function limit($identifier)
    {
        $key = $this->key($identifier);

        $current = $this->getCurrent($key);

        if ($current >= $this->rate->getOperations()) {
            throw LimitExceeded::forIdentifier($identifier, $this->rate);
        }

        $this->updateCounter($key);
    }

    public function limitSilently( $identifier)
    {
        $key = $this->key($identifier);

        $current = $this->getCurrent($key);

        if ($current <= $this->rate->getOperations()) {
            $current = $this->updateCounter($key);
        }

        return Status::from(
            $identifier,
            $current,
            $this->rate->getOperations(),
            time() + $this->ttl($key)
        );
    }

    private function key($identifier)
    {
        return "{$this->keyPrefix}{$identifier}:{$this->rate->getInterval()}";
    }

    private function getCurrent($key)
    {
        return (int) $this->redis->get($key);
    }

    private function updateCounter($key)
    {
        $current = $this->redis->incr($key);

        if ($current === 1) {
            $this->redis->expire($key, $this->rate->getInterval());
        }

        return $current;
    }

    private function ttl($key)
    {
        return max((int) ceil($this->redis->pttl($key) / 1000), 0);
    }
}
