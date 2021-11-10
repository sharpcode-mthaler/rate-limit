<?php



namespace RateLimit;

use RateLimit\Exception\LimitExceeded;
use function floor;
use function time;

final class InMemoryRateLimiter extends ConfigurableRateLimiter implements RateLimiter, SilentRateLimiter
{
    private $store = [];

    public function limit($identifier)
    {
        $key = $this->key($identifier);

        $current = $this->hit($key);

        if ($current > $this->rate->getOperations()) {
            throw LimitExceeded::forIdentifier($identifier, $this->rate);
        }
    }

    public function limitSilently($identifier)
    {
        $key = $this->key($identifier);

        $current = $this->hit($key);

        return Status::from(
            $identifier,
            $current,
            $this->rate->getOperations(),
            $this->store[$key]['reset_time']
        );
    }

    private function key($identifier)
    {
        $interval = $this->rate->getInterval();

        return "$identifier:$interval:" . floor(time() / $interval);
    }

    private function hit($key)
    {
        if (!isset($this->store[$key])) {
            $this->store[$key] = [
                'current' => 1,
                'reset_time' => time() + $this->rate->getInterval(),
            ];
        } elseif ($this->store[$key]['current'] <= $this->rate->getOperations()) {
            $this->store[$key]['current']++;
        }

        return $this->store[$key]['current'];
    }
}
