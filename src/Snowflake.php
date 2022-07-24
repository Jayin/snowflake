<?php

/*
 * This file is part of the godruoyi/php-snowflake.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Godruoyi\Snowflake;

use Godruoyi\Snowflake\exception\InvalidParameterException;
use SebastianBergmann\LinesOfCode\IllogicalValuesException;

class Snowflake
{
    public const MAX_TIMESTAMP_LENGTH = 41;

    public const MAX_DATACENTER_LENGTH = 5;

    public const MAX_WORKID_LENGTH = 5;

    public const MAX_SEQUENCE_LENGTH = 12;

    public const MAX_FIRST_LENGTH = 1;

    /**
     * The data center id.
     *
     * @var int
     */
    protected $datacenter;

    /**
     * The worker id.
     *
     * @var int
     */
    protected $workerid;

    /**
     * The Sequence Resolver instance.
     *
     * @var null|\Godruoyi\Snowflake\SequenceResolver
     */
    protected $sequence;

    /**
     * The start timestamp.
     *
     * @var int
     */
    protected $startTime;

    /**
     * Default sequence resolver.
     *
     * @var null|\Godruoyi\Snowflake\SequenceResolver
     */
    protected $defaultSequenceResolver;

    /**
     * Build Snowflake Instance.
     *
     * @param int $datacenter
     * @param int $workerid
     */
    public function __construct(int $datacenter = 0, int $workerid = 0)
    {
        $maxDataCenter = -1 ^ (-1 << self::MAX_DATACENTER_LENGTH);
        $maxWorkId = -1 ^ (-1 << self::MAX_WORKID_LENGTH);

        if ($datacenter < 0 || $datacenter > $maxDataCenter) {
            throw new InvalidParameterException("`DataCenter` must >= 0 and <= $maxDataCenter");
        }
        if ($workerid < 0 || $workerid > $maxWorkId) {
            throw new InvalidParameterException("`WorkId` must >= 0 and <= $maxWorkId");
        }
        $this->datacenter = $datacenter;
        $this->workerid = $workerid;
    }

    /**
     * Get snowflake id.
     *
     * @return string
     */
    public function id()
    {
        $currentTime = $this->getCurrentMicrotime();
        while (($sequence = $this->callResolver($currentTime)) > (-1 ^ (-1 << self::MAX_SEQUENCE_LENGTH))) {
            usleep(1);
            $currentTime = $this->getCurrentMicrotime();
        }

        $workerLeftMoveLength = self::MAX_SEQUENCE_LENGTH;
        $datacenterLeftMoveLength = self::MAX_WORKID_LENGTH + $workerLeftMoveLength;
        $timestampLeftMoveLength = self::MAX_DATACENTER_LENGTH + $datacenterLeftMoveLength;

        return (string) ((($currentTime - $this->getStartTimeStamp()) << $timestampLeftMoveLength)
            | ($this->datacenter << $datacenterLeftMoveLength)
            | ($this->workerid << $workerLeftMoveLength)
            | ($sequence));
    }

    /**
     * Parse snowflake id.
     */
    public function parseId(string $id, bool $transform = false): array
    {
        $id = decbin($id);

        $data = [
            'timestamp' => substr($id, 0, -22),
            'sequence' => substr($id, -12),
            'workerid' => substr($id, -17, 5),
            'datacenter' => substr($id, -22, 5),
        ];

        return $transform ? array_map(function ($value) {
            return bindec($value);
        }, $data) : $data;
    }

    /**
     * Get current microtime timestamp.
     *
     * @return int
     */
    public function getCurrentMicrotime()
    {
        return floor(microtime(true) * 1000) | 0;
    }

    /**
     * Set start time (millisecond).
     */
    public function setStartTimeStamp(int $startTime)
    {
        $missTime = $this->getCurrentMicrotime() - $startTime;

        if ($missTime < 0) {
            throw new \Exception('The start time cannot be greater than the current time');
        }

        $maxTimeDiff = -1 ^ (-1 << self::MAX_TIMESTAMP_LENGTH);

        if ($missTime > $maxTimeDiff) {
            throw new \Exception(sprintf('The current microtime - starttime is not allowed to exceed -1 ^ (-1 << %d), You can reset the start time to fix this', self::MAX_TIMESTAMP_LENGTH));
        }

        $this->startTime = $startTime;

        return $this;
    }

    /**
     * Get start timestamp (millisecond)
     *
     * @return int
     */
    public function getStartTimeStamp()
    {
        if ($this->startTime > 0) {
            return $this->startTime;
        }

        return $this->defaultStartTimeStamp();
    }

    /**
     *
     * @return float|int
     */
    protected function defaultStartTimeStamp()
    {
        return strtotime(date('Y-m-d')) * 1000;
    }

    /**
     * Set Sequence Resolver.
     *
     * @param callable|SequenceResolver $sequence
     */
    public function setSequenceResolver($sequence)
    {
        $this->sequence = $sequence;

        return $this;
    }

    /**
     * Get Sequence Resolver.
     *
     * @return null|callable|\Godruoyi\Snowflake\SequenceResolver
     */
    public function getSequenceResolver()
    {
        return $this->sequence;
    }

    /**
     * Get Default Sequence Resolver.
     *
     * @return \Godruoyi\Snowflake\SequenceResolver
     */
    public function getDefaultSequenceResolver(): SequenceResolver
    {
        return $this->defaultSequenceResolver ?: $this->defaultSequenceResolver = new RandomSequenceResolver();
    }

    /**
     * Call resolver.
     *
     * @param callable|\Godruoyi\Snowflake\SequenceResolver $resolver
     * @param int                                           $maxSequence
     * @param mixed                                         $currentTime
     *
     * @return int
     */
    protected function callResolver($currentTime)
    {
        $resolver = $this->getSequenceResolver();

        if (is_callable($resolver)) {
            return $resolver($currentTime);
        }

        return is_null($resolver) || !($resolver instanceof SequenceResolver)
            ? $this->getDefaultSequenceResolver()->sequence($currentTime)
            : $resolver->sequence($currentTime);
    }
}
