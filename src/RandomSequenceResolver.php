<?php

/*
 * This file is part of the godruoyi/php-snowflake.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Godruoyi\Snowflake;

use Godruoyi\Snowflake\exception\InvalidTimeException;

class RandomSequenceResolver implements SequenceResolver
{
    /**
     * The las ttimestamp.
     *
     * @var null
     */
    protected $lastTimeStamp = -1;

    /**
     * The sequence.
     *
     * @var int
     */
    protected $sequence = 0;

    /**
     *  {@inheritdoc}
     */
    public function sequence(int $currentTime)
    {
        if ($currentTime < $this->lastTimeStamp) {
            throw new InvalidTimeException("CurrentTimeStamp($currentTime) is latter than LastTimeStamp($this->lastTimeStamp)");
        }
        if ($this->lastTimeStamp === $currentTime) {
            ++$this->sequence;
            $this->lastTimeStamp = $currentTime;

            return $this->sequence;
        }

        $this->sequence = 0;
        $this->lastTimeStamp = $currentTime;

        return 0;
    }
}
