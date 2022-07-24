<?php
/**
 * Author: Jayin Taung <tonjayin@gmail.com>
 */

namespace Tests;

use Godruoyi\Snowflake\Snowflake;

class FakeSnowflake extends Snowflake
{
    private $count = 0;

    public function getCurrentMicrotime()
    {
        if ($this->count >= 1) {
            // simulate time back 5s
            return floor(microtime(true) * 1000) - 5 * 1000;
        }
        $this->count++;
        return parent::getCurrentMicrotime();
    }
}