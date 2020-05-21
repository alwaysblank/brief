<?php
declare(strict_types=1);

namespace AlwaysBlank\Brief;

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

    public function testMakeReturnsSameBriefIfGivenBriefAsArgument(): void
    {
        $Tester = Brief::make(['key' => 'value']);
        $this->assertSame(
            $Tester,
            Brief::make($Tester)
        );
    }

    public function testNewReturnsNewBriefWithValuesOfOldBrief(): void
    {
        $Tester = Brief::make(['key' => 'value'], [
            'aliases' => ['key' => 'uno'],
        ]);
        $New    = (new Brief($Tester, [
            'aliases' => ['key' => 'eins'],
        ]));
        $this->assertEquals(
            'value',
            $New->key
        );
        $this->assertNotSame(
            $Tester,
            $New
        );
        $this->assertEquals('key', $Tester->getAliasedKey('uno'));
        $this->assertEquals('key', $New->getAliasedKey('eins'));
        $this->assertEquals('value', $New->eins);
        $this->assertEquals('value', $New->uno);
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
        $this->expectOutputRegex('/ERR: ProtectedKey :: This key is protected and cannot be used. ::.*/ms');
        Brief::make(['protected' => 'value'], ['logger' => true]);
    }

    public function testAttemptingToUseProtectedKeyToDynamicallySetValueThrowsCorrectException(): void
    {

        $this->expectOutputRegex('/ERR: ProtectedKey :: This key is protected and cannot be used. ::.*/ms');
        $Brief = Brief::make([], ['logger' => true]);
        $Brief->protected = 'value';
        var_dump($Brief);
    }

    public function testAttemptingToPassNonViableInputLogsCorrectData(): void
    {
        $this->expectOutputRegex('/ERR: WrongArgumentType :: Did not pass array or iterable object. ::.*/ms');
        Brief::make(1, ['logger' => true]);
        $this->expectOutputRegex('/ERR: WrongArgumentType :: Did not pass array or iterable object. ::.*/ms');
        Brief::make('test', ['logger' => true]);
        $this->expectOutputRegex('/ERR: WrongArgumentType :: Did not pass array or iterable object. ::.*/ms');
        Brief::make((object)[], ['logger' => true]);
    }

    public function testPassingBooleanTrueAsLoggerResultsInUseOfSystemErrorLog(): void
    {
        $this->expectOutputRegex('/ERR: WrongArgumentType :: Did not pass array or iterable object. ::.*/ms');
        Brief::make(1, ['logger' => true]);
    }

    public function testPassingCallableToLoggerCallsThatFunction(): void
    {
        $this->expectException(\Exception::class);
        Brief::make(1, [
            'logger' => function () {
                throw new \Exception('TestException');
            }
        ]);
        $this->expectOutputRegex('/oh shit.*/ms');
        Brief::make(1, [
            'logger' => function () {
                echo 'oh shit';
            }
        ]);
    }

    public function testReturnsNullIfAttemptToLogWithNoCallable(): void
    {
        /** @noinspection PhpVoidFunctionResultUsedInspection */
        $this->assertNull(Brief::make([])->log('test', 'a test problem'));
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

    public function testNonexistantKeysReturnNull(): void
    {
        $Brief = Brief::make([]);
        $this->assertNull($Brief->fail);
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
            $Brief->debrief(__NAMESPACE__ . '\\makeUppercase')
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
            $Brief->pass(__NAMESPACE__ . '\\makeConcatenated')
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

    public function testFindReturnsNullIfNotGivenAnArrayOrString(): void
    {
        $Brief = Brief::make([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);
        $this->assertNull($Brief->find(2));
        $this->assertNull($Brief->find(new Brief([])));
    }

    public function testCanFindFallbackKey(): void
    {
        $Brief = Brief::make([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);
        $this->assertEquals('value2', $Brief->find(['nonexistant', 'key2']));
    }

    public function testReturnNullIfCannotFindAnyKey(): void
    {
        $Brief = Brief::make([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);
        $this->assertNull($Brief->find(['a', 'b', 'c', 'd']));
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
        $this->assertNull($Brief->uno);
        $this->assertNull($Brief->eins);
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
        $Brief = Brief::make([
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
        $Brief2 = Brief::make([
            'key1' => 'value1',
            'key2' => 'value2',
        ], [
            'aliases' => [],
        ]);
        $this->assertEquals('value1', $Brief->key1);
        $this->assertEquals('value1', $Brief2->key1);
    }

    public function testCreateEmptyBrief(): void
    {
        $Empty = Brief::empty();
        $this->assertInstanceOf(\AlwaysBlank\Brief\EmptyBrief::class, $Empty);
        $this->assertNull($Empty->anything);
    }

    public function testConvertItemsInBriefWithTransform(): void
    {
        $Brief = Brief::make([
            'key1' => ['nested1' => 'value1'],
            'key2' => ['nested2' => 'value2'],
        ]);

        $Returned = $Brief->transform(function ($value, $key, $instance) {
            $instance->$key = Brief::make($value);
        });

        $this->assertInstanceOf(Brief::class, $Brief->key1);
        $this->assertInstanceOf(Brief::class, $Brief->key2);
        $this->assertEquals('value1', $Brief->key1->nested1);
        $this->assertEquals('value2', $Brief->key2->nested2);
        $this->assertEquals($Returned, $Brief);
    }

    public function testConvertItemsIntoNewBriefWithMap(): void
    {
        $Brief = Brief::make([
            'key1' => ['nested1' => 'value1'],
            'key2' => ['nested2' => 'value2'],
        ]);

        $Returned = $Brief->map(function ($value, $key, $instance) {
            $instance->$key = Brief::make($value);
        });

        $this->assertInstanceOf(Brief::class, $Returned->key1);
        $this->assertInstanceOf(Brief::class, $Returned->key2);
        $this->assertEquals('value1', $Returned->key1->nested1);
        $this->assertEquals('value2', $Returned->key2->nested2);
        $this->assertNotEquals($Returned, $Brief);

    }

    public function testSetKeyAndValueDynamically(): void
    {
        $Brief = Brief::make([]);
        $Brief->new_key = 'new value';
        $this->assertEquals('new value', $Brief->new_key);
        $this->assertNotEquals('new value', $Brief->any_key);
    }
}

/**
 * Fake the system call to error_log() so we can test it.
 *
 * @param $msg
 * @param $code
 */
function error_log($msg, $code)
{
    // this will be called from abc\Logger::error
    // instead of the native error_log() function
    echo "ERR: $msg";
}