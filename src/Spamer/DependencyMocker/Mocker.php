<?php

namespace Spamer\DependencyMocker;

use Nette;
use Mockery;

class Mocker
{
	/** @var array */
	public static $bannedClasses;

	/** @var \ReflectionClass */
	private static $reflectedClass;

	/** @var Mockery\Mock */
	private static $mockedClass;

	/**
	 * @param string $className
	 * @return \ReflectionClass|\stdClass
	 */
	public static function mockClassDependencies($className)
	{
		self::$reflectedClass = new \ReflectionClass($className);
		self::$mockedClass = Mockery::mock($className);

		self::mockInjectedMethods($className);
		self::mockInjectedProperties();
		self::mockConstructorDependencies($className);

		return self::$mockedClass;
	}


	/**
	 * @param string $className
	 */
	private static function mockInjectedMethods($className)
	{
		foreach (self::$reflectedClass->getMethods() as $method) {
			if (substr($method->getName(), 0, 6) === 'inject') {
				self::mockDependenciesFromMethod($className, $method->getName());
			}
		}
	}


	private static function mockInjectedProperties()
	{
		/** @var \ReflectionProperty $property */
		foreach (self::$reflectedClass->getProperties() as $property) {
			if (
				Nette\DI\PhpReflection::parseAnnotation($property, 'inject') !== NULL
				||
				Nette\DI\PhpReflection::parseAnnotation($property, 'autowire') !== NULL
			) {
				if ($mockedParameterClass = Nette\DI\PhpReflection::parseAnnotation($property, 'var')) {
					$mockedParameterClass = Nette\DI\PhpReflection::expandClassName(
						$mockedParameterClass,
						Nette\DI\PhpReflection::getDeclaringClass($property)
					);
				}
				self::setProperty($mockedParameterClass, $property);
			}
		}
	}


	/**
	 * @param string $className
	 */
	private static function mockConstructorDependencies($className)
	{
		if (method_exists($className, '__construct')) {
			self::mockDependenciesFromMethod($className, '__construct');
		}
	}


	/**
	 * @param string $className
	 * @param string $methodName
	 */
	private static function mockDependenciesFromMethod($className, $methodName)
	{
		$reflectionMethod = new \ReflectionMethod($className, $methodName);
		$parameters = $reflectionMethod->getParameters();

		/** @var \ReflectionParameter $parameter */
		foreach ($parameters as $parameter) {
			if ($parameter->getClass()) {
				$parameterClass = $parameter->getClass()->getName();
				self::setProperty($parameterClass, $parameter);
			}
		}
	}


	/**
	 * @param array $bannedClasses
	 */
	public static function setBannedClasses($bannedClasses)
	{
		self::$bannedClasses = $bannedClasses;
	}


	/**
	 * @param string $className
	 * @param \ReflectionParameter|\ReflectionProperty $class
	 */
	private static function setProperty($className, $class)
	{
		if ( ! in_array($className, self::$bannedClasses) && $class->getDeclaringClass()->hasProperty($class->getName())) {
			$mockedParameter = Mockery::mock($className);
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
	 */
	public static function callPrivateFunction($object, $method, $arguments = [])
	{
		$reflectionClass = new \ReflectionClass($object);
		$reflectionMethod = $reflectionClass->getMethod($method);
		$reflectionMethod->setAccessible(TRUE);

		return $reflectionMethod->invokeArgs($object, $arguments);
	}
}