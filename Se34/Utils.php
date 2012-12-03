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
	 * Helper na převod řetězců na array.
	 *
	 * Na několika místech umožňuju z pohodlnosti místo array vložit řetězec ve
	 * formátu Neon bez závorek. Tj. například `'a = b, c = d'`, což tenhle helper
	 * převede na `array('a' => 'b', 'c' => 'd')`.
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
	 * Získá návratové typy z `@return` anotace metody.
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
	 * Získá anotace třídy.
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
