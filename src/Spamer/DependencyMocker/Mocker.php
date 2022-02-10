<?php declare(strict_types = 1);

namespace Spamer\DependencyMocker;


class Mocker
{
	/**
	 * @var array
	 */
	public static $bannedClasses = [];

	/**
	 * @var \ReflectionClass
	 */
	private static $reflectedClass;

	/**
	 * @var \Mockery\Mock
	 */
	private static $mockedClass;


	public static function mockClassDependencies(
		string $className
	) : \Mockery\MockInterface
	{
		self::$reflectedClass = new \ReflectionClass($className);
		self::$mockedClass = \Mockery::mock($className);

		self::mockInjectedMethods($className);
		self::mockInjectedProperties();
		self::mockConstructorDependencies($className);

		return self::$mockedClass;
	}


	private static function mockInjectedMethods(
		string $className
	) : void
	{
		foreach (self::$reflectedClass->getMethods() as $method) {
			if (\strpos($method->getName(), 'inject') === 0) {
				self::mockDependenciesFromMethod($className, $method->getName());
			}
		}
	}


	private static function mockInjectedProperties() : void
	{
		/** @var \ReflectionProperty $property */
		foreach (self::$reflectedClass->getProperties() as $property) {
			if (
				ReflectionHelper::parseAnnotation($property, 'inject') !== NULL
				||
				ReflectionHelper::parseAnnotation($property, 'autowire') !== NULL
			) {
				if ($mockedParameterClass = ReflectionHelper::parseAnnotation($property, 'var')) {
					$mockedParameterClass = ReflectionHelper::expandClassName(
						$mockedParameterClass,
						ReflectionHelper::getDeclaringClass($property)
					);
				}
				self::setProperty($mockedParameterClass, $property);
			}
		}
	}


	private static function mockConstructorDependencies(string $className) : void
	{
		if (method_exists($className, '__construct')) {
			self::mockDependenciesFromMethod($className, '__construct');
		}
	}


	private static function mockDependenciesFromMethod(
		string $className,
		string $methodName
	) : void
	{
		$reflectionMethod = new \ReflectionMethod($className, $methodName);
		$parameters = $reflectionMethod->getParameters();

		/** @var \ReflectionParameter $parameter */
		foreach ($parameters as $parameter) {
			if ($parameter->getType() && !$parameter->getType()->isBuiltin()) {
				$name = $parameter->getType()->getName();
				self::setProperty($name, $parameter);
			}
		}
	}


	public static function setBannedClasses(
		array $bannedClasses
	) : void
	{
		self::$bannedClasses = $bannedClasses;
	}


	/**
	 * @param string $className
	 * @param \ReflectionParameter|\ReflectionProperty $class
	 * @throws \ReflectionException
	 */
	private static function setProperty(
		string $className,
		$class
	) : void
	{
		if (
			! in_array($className, self::$bannedClasses, TRUE)
			&& $class->getDeclaringClass()->hasProperty($class->getName())
		) {
			$mockedParameter = \Mockery::mock($className);
			$property = new \ReflectionProperty($class->getDeclaringClass()->getName(), $class->getName());
			$property->setAccessible(TRUE);
			$property->setValue(self::$mockedClass, $mockedParameter);
		}
	}


	/**
	 * @param string $class
	 * @param string $property
	 * @param object $object
	 * @return mixed
	 * @throws \ReflectionException
	 */
	public static function getProperty($class, $property, $object)
	{
		$property = new \ReflectionProperty($class, $property);
		$property->setAccessible(TRUE);
		return $property->getValue($object);
	}


	/**
	 * Calls private method and returns result.
	 *
	 * @param object $object
	 * @param string $method
	 * @param array $arguments
	 * @return mixed
	 * @throws \ReflectionException
	 */
	public static function callPrivateFunction($object, $method, $arguments = [])
	{
		$reflectionClass = new \ReflectionClass($object);
		$reflectionMethod = $reflectionClass->getMethod($method);
		$reflectionMethod->setAccessible(TRUE);

		return $reflectionMethod->invokeArgs($object, $arguments);
	}
}
