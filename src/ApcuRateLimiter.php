<?php



namespace RateLimit;

use RateLimit\Exception\CannotUseRateLimiter;
use RateLimit\Exception\LimitExceeded;
use function apcu_fetch;
use function apcu_inc;
use function apcu_store;
use function extension_loaded;
use function ini_get;
use function max;
use function sprintf;
use function time;

final class ApcuRateLimiter extends ConfigurableRateLimiter implements RateLimiter, SilentRateLimiter
{
    private $keyPrefix;

    public function __construct(Rate $rate, $keyPrefix = '')
    {
        if (!extension_loaded('apcu') || ini_get('apc.enabled') === '0') {
            throw new CannotUseRateLimiter('APCu extension is not loaded or not enabled.');
        }

        if (ini_get('apc.use_request_time') === '1') {
            throw new CannotUseRateLimiter('APCu ini configuration "apc.use_request_time" should be set to "0".');
        }

        parent::__construct($rate);
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
        return (int) apcu_fetch($limitKey);
    }

    private function updateCounterAndTime($limitKey, $timeKey)
    {
        $current = $this->updateCounter($limitKey);

        if ($current === 1) {
            apcu_store($timeKey, time(), $this->rate->getInterval());
        }

        return $current;
    }

    private function updateCounter($limitKey)
    {
        $current = apcu_inc($limitKey, 1, $success, $this->rate->getInterval());

        return $current === false ? 1 : $current;
    }

    private function getElapsedTime($timeKey)
    {
        return time() - (int) apcu_fetch($timeKey);
    }
}
