<?php

/*
 * This file is part of the godruoyi/php-snowflake.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests;

use Godruoyi\Snowflake\exception\InvalidParameterException;
use Godruoyi\Snowflake\exception\InvalidTimeException;
use Godruoyi\Snowflake\RandomSequenceResolver;
use Godruoyi\Snowflake\SequenceResolver;
use Godruoyi\Snowflake\Snowflake;

class SnowflakeTest extends TestCase
{
    public function testBasic()
    {
        $snowflake = new Snowflake();

        $this->assertTrue(!empty($snowflake->id()));
        $this->assertTrue(strlen($snowflake->id()) <= 19);
    }

    public function testInvalidDatacenterIDAndWorkID()
    {
        try {
            new Snowflake(-1, 0);
        } catch (\Exception $e) {
            $this->assertInstanceOf(InvalidParameterException::class, $e);
        }
        try {
            new Snowflake(32, 0);
        } catch (\Exception $e) {
            $this->assertInstanceOf(InvalidParameterException::class, $e);
        }
        try {
            new Snowflake(0, -1);
        } catch (\Exception $e) {
            $this->assertInstanceOf(InvalidParameterException::class, $e);
        }
        try {
            new Snowflake(0, 32);
        } catch (\Exception $e) {
            $this->assertInstanceOf(InvalidParameterException::class, $e);
        }
    }

    public function testExtends()
    {
        $snowflake = new Snowflake(1, 1);
        $snowflake->setSequenceResolver(function ($currentTime) {
            return 999;
        });

        $id = $snowflake->id();

        $this->assertTrue(1 === $snowflake->parseId($id, true)['datacenter']);
        $this->assertTrue(1 === $snowflake->parseId($id, true)['workerid']);
        $this->assertTrue(999 === $snowflake->parseId($id, true)['sequence']);
    }

    public function testBatch()
    {
        $snowflake = new Snowflake(1, 1);
        $snowflake->setSequenceResolver(function ($currentTime) {
            static $lastTime;
            static $sequence;

            if ($lastTime === $currentTime) {
                ++$sequence;
            } else {
                $sequence = 0;
            }

            $lastTime = $currentTime;

            return $sequence;
        });

        $datas = [];

        for ($i = 0; $i < 10000; ++$i) {
            $id = $snowflake->id();
            $datas[$id] = 1;
        }

        $this->assertTrue(10000 === count($datas));
    }

    public function testParseId()
    {
        $snowflake = new Snowflake(1, 1);
        $star_timestamp = strtotime(date('Y-m-d')) * 1000;
        $snowflake->setStartTimeStamp($star_timestamp);
        $id = $snowflake->id();

        $now = floor(microtime(true) * 1000);
        $data = $snowflake->parseId($id, true);
        $this->assertSame($data['workerid'], 1);
        $this->assertSame($data['datacenter'], 1);
        $this->assertSame($data['sequence'], 0);
        $this->assertTrue($data['timestamp'] >= ($now - $star_timestamp));
    }

    public function testGetCurrentMicrotime()
    {
        $snowflake = new Snowflake(1, 1);
        $now = floor(microtime(true) * 1000) | 0;
        $time = $snowflake->getCurrentMicrotime();

        $this->assertTrue($time >= $now);
    }

    public function testSetStartTimeStamp()
    {
        $snowflake = new Snowflake(1, 1);

        $snowflake->setStartTimeStamp(1);
        $this->assertTrue(1 === $snowflake->getStartTimeStamp());
    }

    public function testSetStartTimeStampMaxValueIsOver()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The current microtime - starttime is not allowed to exceed -1 ^ (-1 << 41), You can reset the start time to fix this');

        $snowflake = new Snowflake(1, 1);
        $snowflake->setStartTimeStamp(strtotime('1900-01-01') * 1000);
    }

    public function testSetStartTimeStampCannotMoreThatCurrentTime()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The start time cannot be greater than the current time');

        $snowflake = new Snowflake(1, 1);
        $snowflake->setStartTimeStamp(strtotime('3000-01-01') * 1000);
    }

    public function testGetStartTimeStamp()
    {
        $snowflake = new Snowflake(1, 1);
        $defaultTime = date('Y-m-d');

        $this->assertTrue($snowflake->getStartTimeStamp() === (strtotime($defaultTime) * 1000));

        $snowflake->setStartTimeStamp(strtotime($defaultTime) * 1000);
        $this->assertTrue(strtotime($defaultTime) * 1000 === $snowflake->getStartTimeStamp());
    }

    public function testcallResolver()
    {
        $snowflake = new Snowflake(1, 1);
        $snowflake->setSequenceResolver(function ($currentTime) {
            return 999;
        });

        $seq = $snowflake->getSequenceResolver();

        $this->assertTrue($seq instanceof \Closure);
        $this->assertTrue(999 === $seq(0));
    }

    public function testGetSequenceResolver()
    {
        $snowflake = new Snowflake(1, 1);
        $this->assertTrue(is_null($snowflake->getSequenceResolver()));

        $snowflake->setSequenceResolver(function () {
            return 1;
        });

        $this->assertTrue(is_callable($snowflake->getSequenceResolver()));
    }

    public function testGetDefaultSequenceResolver()
    {
        $snowflake = new Snowflake(1, 1);
        $this->assertInstanceOf(SequenceResolver::class, $snowflake->getDefaultSequenceResolver());
        $this->assertInstanceOf(RandomSequenceResolver::class, $snowflake->getDefaultSequenceResolver());
    }

    public function testException()
    {
        $snowflake = new Snowflake();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The start time cannot be greater than the current time');

        $snowflake->setStartTimeStamp(time() * 1000 + 1);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The current microtime - starttime is not allowed to exceed -1 ^ (-1 << 41), You can reset the start time to fix this');

        $snowflake->setStartTimeStamp(strtotime('1900-01-01') * 1000);
    }

    public function testTimeCallback()
    {
        $snowflake = new FakeSnowflake();

        // For RandomSequenceResolver
        $snowflake->setSequenceResolver(new RandomSequenceResolver());
        // init lastTime
        $snowflake->id();
        sleep(1);
        $this->expectException(InvalidTimeException::class);
        $snowflake->id();
    }
}
