<?php
declare(strict_types=1);

use AlwaysBlank\Brief\Brief;
use AlwaysBlank\Brief\Exceptions\CannotSetProtectedKeyException;
use PHPUnit\Framework\TestCase;

final class BriefTest extends TestCase
{
    public function testCanBeCorrectlyInstantiated(): void
    {
        $this->assertInstanceOf(
            Brief::class,
            Brief::make([])
        );
    }

    public function testReturnsSameBriefIfGivenBriefAsArgument(): void
    {
        $Tester = Brief::make(['key' => 'value']);
        $this->assertEquals(
            $Tester,
            Brief::make($Tester)
        );
    }

    public function testAttemptingToUseProtectedKeysThrowsCorrectException(): void
    {
        $this->expectException(CannotSetProtectedKeyException::class);
        Brief::make(['protected' => 'value']);
    }

    public function testCanGetValueViaProp(): void
    {
        $value = 'test value';
        $Brief = Brief::make(['test' => $value]);
        $this->assertEquals(
            $value,
            $Brief->test
        );
    }

    public function testNonexistantKeysReturnBooleanFalse(): void
    {
        $Brief = Brief::make([]);
        $this->assertFalse($Brief->fail);
    }

    public function testCallFunctionWithCallMethod(): void
    {
        function makeUppercase($Brief)
        {
            return strtoupper($Brief->test);
        }

        $original = 'test';
        $result = strtoupper($original);

        $Brief = Brief::make(['test' => $original]);
        $this->assertEquals(
            $result,
            $Brief->call('makeUppercase')
        );
    }

    public function testCallFunctionWithCallUnpackedMethod(): void
    {
        function makeConcatenated($one, $two)
        {
            return $one . $two;
        }

        $one = 'Star';
        $two = 'Wars';
        $result = $one . $two;

        $Brief = Brief::make([$one, $two]);
        $this->assertEquals(
            $result,
            $Brief->callUnpacked('makeConcatenated')
        );
    }
}
