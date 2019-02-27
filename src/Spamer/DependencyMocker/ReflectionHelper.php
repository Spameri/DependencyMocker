<?php declare(strict_types = 1);

namespace Spamer\DependencyMocker;


class ReflectionHelper
{

	/**
	 * @var array
	 */
	private static $cache;

	/**
	 * @var array
	 */
	private static $builtinTypes = [
		'string' => 1, 'int' => 1, 'float' => 1, 'bool' => 1, 'array' => 1, 'object' => 1,
		'callable' => 1, 'iterable' => 1, 'void' => 1,
	];


	public static function parseAnnotation(
		\ReflectionProperty $ref,
		string $name
	) : string
	{
		if (preg_match("#[\\s*]@$name(?:\\s++([^@]\\S*)?|$)#", trim($ref->getDocComment(), '/*'), $m)) {
			return $m[1] ?? '';
		}

		return '';
	}



	public static function getDeclaringClass(
		\ReflectionProperty $prop
	) : \ReflectionClass
	{
		foreach ($prop->getDeclaringClass()->getTraits() as $trait) {
			if ($trait->hasProperty($prop->getName())) {
				return self::getDeclaringClass($trait->getProperty($prop->getName()));
			}
		}

		return $prop->getDeclaringClass();
	}


	public static function expandClassName(
		string $name,
		\ReflectionClass $rc
	) : string
	{
		$lower = strtolower($name);

		if (self::isBuiltinType($lower)) {
			return $lower;

		} elseif ($lower === 'self' || $lower === 'static' || $lower === '$this') {
			return $rc->getName();

		} elseif ($name[0] === '\\') { // fully qualified name
			return ltrim($name, '\\');
		}

		$uses = & self::$cache[$rc->getName()];
		if ($uses === NULL) {
			self::$cache = self::parseUseStatements(file_get_contents($rc->getFileName()), $rc->getName()) + self::$cache;
			$uses = & self::$cache[$rc->getName()];
		}
		$parts = explode('\\', $name, 2);
		if (isset($uses[$parts[0]])) {
			$parts[0] = $uses[$parts[0]];
			return implode('\\', $parts);

		} elseif ($rc->inNamespace()) {
			return $rc->getNamespaceName() . '\\' . $name;

		} else {
			return $name;
		}
	}


	public static function isBuiltinType(
		string $type
	) : bool
	{
		return isset(self::$builtinTypes[strtolower($type)]);
	}


	private static function parseUseStatements($code, $forClass = null)
	{
		$tokens = PHP_VERSION_ID >= 70000 ? token_get_all($code, TOKEN_PARSE) : token_get_all($code);
		$namespace = $class = $classLevel = $level = null;
		$res = $uses = [];

		while ($token = current($tokens)) {
			next($tokens);
			switch (is_array($token) ? $token[0] : $token) {
				case T_NAMESPACE:
					$namespace = ltrim(self::fetch($tokens, [T_STRING, T_NS_SEPARATOR]) . '\\', '\\');
					$uses = [];
					break;

				case T_CLASS:
				case T_INTERFACE:
				case T_TRAIT:
					if ($name = self::fetch($tokens, T_STRING)) {
						$class = $namespace . $name;
						$classLevel = $level + 1;
						$res[$class] = $uses;
						if ($class === $forClass) {
							return $res;
						}
					}
					break;

				case T_USE:
					while (!$class && ($name = self::fetch($tokens, [T_STRING, T_NS_SEPARATOR]))) {
						$name = ltrim($name, '\\');
						if (self::fetch($tokens, '{')) {
							while ($suffix = self::fetch($tokens, [T_STRING, T_NS_SEPARATOR])) {
								if (self::fetch($tokens, T_AS)) {
									$uses[self::fetch($tokens, T_STRING)] = $name . $suffix;
								} else {
									$tmp = explode('\\', $suffix);
									$uses[end($tmp)] = $name . $suffix;
								}
								if (!self::fetch($tokens, ',')) {
									break;
								}
							}

						} elseif (self::fetch($tokens, T_AS)) {
							$uses[self::fetch($tokens, T_STRING)] = $name;

						} else {
							$tmp = explode('\\', $name);
							$uses[end($tmp)] = $name;
						}
						if (!self::fetch($tokens, ',')) {
							break;
						}
					}
					break;

				case T_CURLY_OPEN:
				case T_DOLLAR_OPEN_CURLY_BRACES:
				case '{':
					$level++;
					break;

				case '}':
					if ($level === $classLevel) {
						$class = $classLevel = null;
					}
					$level--;
			}
		}

		return $res;
	}


	private static function fetch(&$tokens, $take)
	{
		$res = null;
		while ($token = current($tokens)) {
			[$token, $s] = is_array($token) ? $token : [$token, $token];
			if (in_array($token, (array) $take, true)) {
				$res .= $s;
			} elseif (!in_array($token, [T_DOC_COMMENT, T_WHITESPACE, T_COMMENT], true)) {
				break;
			}
			next($tokens);
		}
		return $res;
	}
}
