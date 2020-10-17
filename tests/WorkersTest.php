<?php


use AlwaysBlank\Brief\Workers;
use PHPUnit\Framework\TestCase;

class WorkersTest extends TestCase
{

    public function testCallingNonexistentWorkerReturnsNull()
    {
        $Workers = new Workers();
        $this->assertNull($Workers->call('does-not-exist'));
    }
}
