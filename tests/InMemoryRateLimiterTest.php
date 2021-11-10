<?php

namespace RateLimit\Tests;

use RateLimit\InMemoryRateLimiter;
use RateLimit\Rate;
use RateLimit\RateLimiter;

class InMemoryRateLimiterTest extends RateLimiterTest
{
    protected function getRateLimiter(Rate $rate)
    {
        return new InMemoryRateLimiter($rate);
    }
}
