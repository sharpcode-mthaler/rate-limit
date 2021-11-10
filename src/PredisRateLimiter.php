<?php



namespace RateLimit;

use Predis\ClientInterface;
use RateLimit\Exception\LimitExceeded;
use function ceil;
use function max;
use function time;

final class PredisRateLimiter extends ConfigurableRateLimiter implements RateLimiter, SilentRateLimiter
{
    private $predis;
    private $keyPrefix;

    public function __construct(Rate $rate, ClientInterface $predis, $keyPrefix = '')
    {
        parent::__construct($rate);
        $this->predis = $predis;
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

    public function limitSilently($identifier)
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
        return (int) $this->predis->get($key);
    }

    private function updateCounter($key)
    {
        $current = $this->predis->incr($key);

        if ($current === 1) {
            $this->predis->expire($key, $this->rate->getInterval());
        }

        return $current;
    }

    private function ttl($key)
    {
        return max((int) ceil($this->predis->pttl($key) / 1000), 0);
    }
}
