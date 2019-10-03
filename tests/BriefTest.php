<?php
declare(strict_types=1);

use AlwaysBlank\Brief\Brief;
use AlwaysBlank\Brief\Exceptions\CannotSetProtectedKeyException;
use AlwaysBlank\Brief\Exceptions\WrongArgumentTypeException;
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

    public function testCanAcceptObjectAsArgument(): void
    {
        $Tester = Brief::make((object)['key' => 'value']);
        $this->assertEquals(
            $Tester->key,
            'value'
        );
    }

    public function testAttemptingToUseProtectedKeysThrowsCorrectException(): void
    {
        $this->expectException(CannotSetProtectedKeyException::class);
        Brief::make(['protected' => 'value']);
    }

    public function testAttemptingToPassNonViableInputThrowsCorrectException(): void
    {
        $this->expectException(WrongArgumentTypeException::class);
        Brief::make(1);
        Brief::make('test');
        Brief::make((object)[]);
    }

    public function testAttempingToPassBooleanAsInputCreatesEmptyBrief(): void
    {
        $Brief = Brief::make(true);
        $this->assertEmpty($Brief->getKeyed());
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
        $result   = strtoupper($original);

        $Brief = Brief::make(['test' => $original]);
        $this->assertEquals(
            $result,
            $Brief->debrief('makeUppercase')
        );
    }

    public function testCallFunctionWithCallUnpackedMethod(): void
    {
        function makeConcatenated($one, $two)
        {
            return $one . $two;
        }

        $one    = 'Star';
        $two    = 'Wars';
        $result = $one . $two;

        $Brief = Brief::make([$one, $two]);
        $this->assertEquals(
            $result,
            $Brief->pass('makeConcatenated')
        );
    }

    public function testSetArgumentAfterInstantiation(): void
    {
        $value       = 'value';
        $Brief       = Brief::make([]);
        $Brief->test = $value;

        $this->assertEquals(
            $value,
            $Brief->test
        );
    }

    public function testPassKeyedArrayToCalledFunction(): void
    {
        $array = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];
        $Brief = Brief::make($array);
        $this->assertTrue($Brief->passArray(function ($array) {
            return is_array($array) && isset($array['key1']);
        }));
    }

    public function testPassOrderedArrayToCalledFunction(): void
    {
        $array = [
            'value1',
            'value2',
        ];
        $Brief = Brief::make($array);
        $this->assertEquals(
            $array,
            $Brief->passArray(function ($array) {
                $tmp = [];
                foreach ($array as $value) {
                    $tmp[] = $value;
                }

                return $tmp;
            }, false)
        );
        $this->assertTrue($Brief->passArray(function ($array) {
            return $array[0] === 'value1';
        }));
    }

    public function testIfItemIsSetInBrief(): void
    {
        $Brief = Brief::make([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);
        $this->assertTrue(isset($Brief->key1));
        $this->assertFalse(isset($Brief->key3));
    }

    public function testCanFindSingleKey(): void
    {
        $Brief = Brief::make([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);
        $this->assertEquals('value1', $Brief->find(['key1']));
    }

    public function testCanFindUnderstandString(): void
    {
        $Brief = Brief::make([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);
        $this->assertEquals('value1', $Brief->find('key1'));
    }

    public function testFindReturnsFalseIfNotGivenAnArrayOrString(): void
    {
        $Brief = Brief::make([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);
        $this->assertFalse($Brief->find(2));
        $this->assertFalse($Brief->find(new Brief([])));
    }

    public function testCanFindFallbackKey(): void
    {
        $Brief = Brief::make([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);
        $this->assertEquals('value2', $Brief->find(['nonexistant', 'key2']));
    }

    public function testReturnFalseIfCannotFindAnyKey(): void
    {
        $Brief = Brief::make([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);
        $this->assertFalse($Brief->find(['a', 'b', 'c', 'd']));
    }

    public function testCanSetAlias(): void
    {
        $Brief  = Brief::make([
            'key1' => 'value1',
            'key2' => 'value2',
        ], [
            'aliases' => ['key1' => ['uno', 'eins']],
        ]);
        $Brief2 = Brief::make([
            'key1' => 'value1',
            'key2' => 'value2',
        ], [
            'alias' => ['key1' => ['uno', 'eins']],
        ]);
        $this->assertEquals('key1', $Brief->getAliasedKey('uno'));
        $this->assertEquals('key1', $Brief->getAliasedKey('eins'));
        $this->assertEquals('key1', $Brief2->getAliasedKey('uno'));
        $this->assertEquals('key1', $Brief2->getAliasedKey('eins'));
    }

    public function testCanGetValueFromAliasedKey(): void
    {
        $Brief = Brief::make([
            'key1' => 'value1',
            'key2' => 'value2',
        ], [
            'aliases' => ['key1' => ['uno', 'eins']],
        ]);
        $this->assertEquals('value1', $Brief->uno);
        $this->assertEquals('value1', $Brief->eins);
    }

    public function testCanGetValueFromAliasedKeyString(): void
    {
        $Brief = Brief::make([
            'key1' => 'value1',
            'key2' => 'value2',
        ], [
            'aliases' => [
                'key1' => ['eins'],
                'eins' => ['uno'],
            ],
        ]);
        $this->assertEquals('value1', $Brief->uno);
    }

    public function testCannotCreateInfiniteLoopOfAliases(): void
    {
        $Brief = Brief::make([
            'key1' => 'value1',
            'key2' => 'value2',
        ], [
            'aliases' => [
                'uno'  => ['eins'],
                'eins' => ['uno'],
            ],
        ]);
        $this->assertFalse($Brief->uno);
        $this->assertFalse($Brief->eins);
    }

    public function testAliasReturnsFalseIfPassedAuthoritativeKey(): void
    {
        $Brief = Brief::make([
            'key1' => 'value1',
            'key2' => 'value2',
        ], [
            'aliases' => ['key1' => ['uno', 'eins']],
        ]);
        $this->assertFalse($Brief->getAliasedKey('key1'));
    }

    public function testSetValueThroughAlias(): void
    {
        $Brief          = Brief::make([], ['aliases' => ['real_key' => ['pointer']]]);
        $Brief->pointer = 'value';
        $this->assertEquals('value', $Brief->real_key);
        $this->assertEquals('value', $Brief->pointer);
        $Brief->real_key = 'new value';
        $this->assertEquals('new value', $Brief->real_key);
        $this->assertEquals('new value', $Brief->pointer);
    }

    public function testAliasKeysAtInstationation(): void
    {
        $Brief = Brief::make([
            'pointer' => 'the value',
        ], [
            'aliases' => [
                'key1' => ['pointer'],
            ]
        ]);

        $this->assertEquals($Brief->key1, $Brief->pointer, "Value for alias and key do not match");
        $this->assertEquals('the value', $Brief->key1, "The key does not return the correct value");
        $this->assertEquals('the value', $Brief->pointer, "The alias does not return the correct value");
    }

    public function testAllowStringInsteadOfArrayForAliasArgument(): void
    {
        $Brief  = Brief::make([
            'key1' => 'value1',
            'key2' => 'value2',
        ], [
            'aliases' => ['key1' => 'uno'],
        ]);
        $this->assertEquals('key1', $Brief->getAliasedKey('uno'));
    }

    public function testAliasSettingParseNoResults(): void
    {
        $Brief  = Brief::make([
            'key1' => 'value1',
            'key2' => 'value2',
        ], [
            'aliases' => [[], 3],
        ]);
        $Brief2  = Brief::make([
            'key1' => 'value1',
            'key2' => 'value2',
        ], [
            'aliases' => [],
        ]);
        $this->assertEquals('value1', $Brief->key1);
        $this->assertEquals('value1', $Brief2->key1);
    }
}
