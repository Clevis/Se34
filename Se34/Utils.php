<?php

namespace Se34;

use Nette\Utils\Neon;
use Nette\Reflection\Method;

/**
 * @author Václav Šír
 */
class Utils
{

	/**
	 * Helper for string to array conversion.
	 *
	 * Here or ther I enable to use strings instead of arrays in parameters
	 * of some methods. If you like the short array definition from PHP 5.4,
	 * then you must like this, even better if you still have to rely on
	 * PHP 5.3...
	 *
	 * The format is basically the Neon format ({@link http://ne-on.org/}, just
	 * the surrounding brackets are added. So `a = b, e: mc2` will translate to
	 * `array('a' => 'b', 'e' => 'mc2')` et cetera.
	 *
	 * @param array|string $value
	 * @return array
	 */
	public static function strToArray($value)
	{
		if (!is_array($value))
		{
			$value = Neon::decode('[' . $value . ']');
		}
		return $value;
	}

	/**
	 * Gains possible return type from the `return` method annotation.
	 *
	 * @param string $className
	 * @param string $methodName
	 * @return array
	 */
	public static function getReturnTypes($className, $methodName)
	{
		$methodName = Method::from($className, $methodName);
		list($types) = preg_split('~\s~', $methodName->getAnnotation('return'), 2);
		return explode('|', $types);
	}

	/**
	 * Gets annotations of a class. Not its predecessors, only of this class.
	 *
	 * @param object|string $object
	 * @return array
	 */
	public static function getClassAnnotations($object)
	{
		$classReflection = new \ReflectionClass($object);
		$docBlock = $classReflection->getDocComment();
		$lines = explode("\n", $docBlock);
		$annotations = array();
		foreach ($lines as $line)
		{
			if (preg_match('~^(((\s*\*)|/\*\*)\s*@)(?<annotationName>[^\s]+)\s+(?<annotationValue>.*)~', $line, $matches))
			{
				$annotations[$matches['annotationName']][] = $matches['annotationValue'];
			}
		}
		return $annotations;
	}
}
