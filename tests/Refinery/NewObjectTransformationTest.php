<?php
/* Copyright (c) 1998-2019 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * @author  Niels Theen <ntheen@databay.de>
 */

namespace ILIAS\Refinery;

use ILIAS\Data\Result\Ok;
use ILIAS\Refinery\To\Transformation\NewObjectTransformation;

require_once('./libs/composer/vendor/autoload.php');

class NewObjectTransformationTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @throws \ReflectionException
	 */
	public function testNewObjectTransformation()
	{
		$transformation = new NewObjectTransformation(MyClass::class);

		$object = $transformation->transform(array('hello', 42));

		$result = $object->myMethod();

		$this->assertEquals(array('hello', 42), $result);
	}

	/**
	 * @expectedException \TypeError
	 */
	public function testNewObjectTransformationThrowsTypeErrorOnInvalidConstructorArguments()
	{
		$transformation = new NewObjectTransformation(MyClass::class);

		$object = $transformation->transform(array('hello', 'world'));

		$this->fail();
	}

	/**
	 * @throws \ReflectionException
	 */
	public function testNewObjectApply()
	{
		$transformation = new NewObjectTransformation(MyClass::class);

		$resultObject = $transformation->applyTo(new Ok(array('hello', 42)));

		$object = $resultObject->value();

		$result = $object->myMethod();

		$this->assertEquals(array('hello', 42), $result);
	}

	/**
	 * @expectedException \TypeError
	 */
	public function testNewObjectApplyResultsErrorObjectOnInvalidConstructorArguments()
	{
		$transformation = new NewObjectTransformation(MyClass::class);

		$resultObject = $transformation->applyTo(new Ok(array('hello', 'world')));

		$this->assertTrue($resultObject->isError());
	}

}

class MyClass
{
	private $string;

	private $integer;

	public function __construct(string $string, int $integer)
	{
		$this->string = $string;
		$this->integer = $integer;
	}

	public function myMethod()
	{
		return array($this->string, $this->integer);
	}
}
