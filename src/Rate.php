<?php



namespace RateLimit;

class Rate
{
    protected $operations;
    protected $interval;

    final protected function __construct($operations, $interval)
    {
        if ($operations <= 0) {
            throw new \InvalidArgumentException('Quota must be greater than zero');
        }
        if ($interval <= 0) {
            throw new \InvalidArgumentException('Seconds interval must be greater than zero');
        }
        $this->operations = $operations;
        $this->interval = $interval;
    }

    public static function perSecond($operations)
    {
        return new static($operations, 1);
    }

    public static function perMinute($operations)
    {
        return new static($operations, 60);
    }

    public static function perHour($operations)
    {
        return new static($operations, 3600);
    }

    public static function perDay($operations)
    {
        return new static($operations, 86400);
    }

    public static function custom($operations, $interval)
    {
        return new static($operations, $interval);
    }

    public function getOperations()
    {
        return $this->operations;
    }

    public function getInterval()
    {
        return $this->interval;
    }
}
