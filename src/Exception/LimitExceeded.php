<?php



namespace RateLimit\Exception;

use RateLimit\Rate;

final class LimitExceeded extends RateLimitException
{
    private $identifier;
    private $rate;

    public static function forIdentifier($identifier, Rate $rate)
    {
        $exception = new self(sprintf(
            'Limit has been exceeded for identifier "%s".',
            $identifier
        ));

        $exception->identifier = $identifier;
        $exception->rate = $rate;

        return $exception;
    }

    public function getIdentifier()
    {
        return $this->identifier;
    }

    public function getRate()
    {
        return $this->rate;
    }
}
