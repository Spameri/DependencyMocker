<?php

require_once __DIR__ . '/../../bootstrap.php';

class TestClass
{
	private function saltPassword($string)
	{
		return $string . 'salt';
	}
}



class TestCallPrivateFunction extends \Tester\TestCase
{
	public function testCall()
	{
		$testClass = new TestClass();

		$result = \Spamer\DependencyMocker\Mocker::callPrivateFunction($testClass, 'saltPassword', ['string']);

		\Tester\Assert::same('stringsalt', $result);
	}

}
(new TestCallPrivateFunction())->run();
