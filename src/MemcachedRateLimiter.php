<?php



namespace RateLimit;

use Memcached;
use RateLimit\Exception\CannotUseRateLimiter;
use RateLimit\Exception\LimitExceeded;
use function max;
use function sprintf;
use function time;

final class MemcachedRateLimiter extends ConfigurableRateLimiter implements RateLimiter, SilentRateLimiter
{
    const MEMCACHED_SECONDS_LIMIT = 2592000; // 30 days in seconds

    private $memcached;
    private $keyPrefix;

    public function __construct(Rate $rate, Memcached $memcached, $keyPrefix = '')
    {
        // @see https://www.php.net/manual/en/memcached.increment.php#111187
        if ($memcached->getOption(Memcached::OPT_BINARY_PROTOCOL) !== 1) {
            throw new CannotUseRateLimiter('Memcached "OPT_BINARY_PROTOCOL" option should be set to "true".');
        }

        parent::__construct($rate);
        $this->memcached = $memcached;
        $this->keyPrefix = $keyPrefix;
    }

    public function limit($identifier)
    {
        $limitKey = $this->limitKey($identifier);

        $current = $this->getCurrent($limitKey);
        if ($current >= $this->rate->getOperations()) {
            throw LimitExceeded::forIdentifier($identifier, $this->rate);
        }

        $this->updateCounter($limitKey);
    }

    public function limitSilently($identifier)
    {
        $limitKey = $this->limitKey($identifier);
        $timeKey = $this->timeKey($identifier);

        $current = $this->getCurrent($limitKey);
        if ($current <= $this->rate->getOperations()) {
            $current = $this->updateCounterAndTime($limitKey, $timeKey);
        }

        return Status::from(
            $identifier,
            $current,
            $this->rate->getOperations(),
            time() + max(0, $this->rate->getInterval() - $this->getElapsedTime($timeKey))
        );
    }

    private function limitKey($identifier)
    {
        return sprintf('%s%s:%d', $this->keyPrefix, $identifier, $this->rate->getInterval());
    }

    private function timeKey($identifier)
    {
        return sprintf('%s%s:%d:time', $this->keyPrefix, $identifier, $this->rate->getInterval());
    }

    private function getCurrent($limitKey)
    {
        return (int) $this->memcached->get($limitKey);
    }

    private function updateCounterAndTime($limitKey, $timeKey)
    {
        $current = $this->updateCounter($limitKey);

        if ($current === 1) {
            $this->memcached->add($timeKey, time(), $this->intervalToMemcachedTime($this->rate->getInterval()));
        }

        return $current;
    }

    private function updateCounter($limitKey)
    {
        $current = $this->memcached->increment($limitKey, 1, 1, $this->intervalToMemcachedTime($this->rate->getInterval()));

        return $current === false ? 1 : $current;
    }

    private function getElapsedTime($timeKey)
    {
        return time() - (int) $this->memcached->get($timeKey);
    }

    /**
     * Interval to Memcached expiration time.
     *
     * @see https://www.php.net/manual/en/memcached.expiration.php
     *
     * @param int $interval
     * @return int
     */
    private function intervalToMemcachedTime($interval)
    {
        return $interval <= self::MEMCACHED_SECONDS_LIMIT ? $interval : time() + $interval;
    }
}
