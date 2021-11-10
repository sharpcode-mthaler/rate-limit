<?php



namespace RateLimit;

abstract class ConfigurableRateLimiter
{
    protected $rate;

    public function __construct(Rate $rate)
    {
        $this->rate = $rate;
    }
}
