<?php

require_once __DIR__ . '/../../bootstrap.php';

class ClassToMock
{

}

class TestDependenciesClass
{

	/**
	 * @var \ClassToMock
	 */
	private $classToMock;


	public function __construct(
		ClassToMock $classToMock
	)
	{
		$this->classToMock = $classToMock;
	}

}



class TestMockDependencies extends \Tester\TestCase
{
	public function testCall()
	{
		$mocked = \Spamer\DependencyMocker\Mocker::mockClassDependencies(TestDependenciesClass::class);

		\Tester\Assert::true($mocked instanceof \Mockery\MockInterface);

		$property = \Spamer\DependencyMocker\Mocker::getProperty(
			TestDependenciesClass::class,
			'classToMock',
			$mocked
		);

		\Tester\Assert::true($property instanceof \Mockery\MockInterface);
	}

}
(new TestMockDependencies())->run();
