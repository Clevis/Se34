<?php

namespace Se34;

/**
 * Object that represents a DOM element.
 *
 * Main purpose of this class is, that I don't want to write the crazy name
 * of the class `PHPUnit_Extensions_Selenium2TestCase_Element`.
 *
 * @author Václav Šír
 */
class Element extends \PHPUnit_Extensions_Selenium2TestCase_Element implements IElementsFinder
{

	/**
	 * Creates the element object from a value returned by Selenium WebDriver.
	 *
	 * Almost exact copy of its parent, but uses `static` instead of `self`.
	 *
	 * @param array $value
	 * @param \PHPUnit_Extensions_Selenium2TestCase_URL $parentFolder
	 * @param \PHPUnit_Extensions_Selenium2TestCase_Driver $driver
	 * @return Element
	 * @throws \InvalidArgumentException
	 */
	public static function fromResponseValue(array $value, \PHPUnit_Extensions_Selenium2TestCase_URL $parentFolder, \PHPUnit_Extensions_Selenium2TestCase_Driver $driver)
	{
		if (!isset($value['ELEMENT']))
		{
			throw new \InvalidArgumentException('Element not found.');
		}
		$url = $parentFolder->descend($value['ELEMENT']);
		return new static($driver, $url);
	}

	/**
	 * Finds an element in the scope of this element.
	 *
	 * @param \PHPUnit_Extensions_Selenium2TestCase_ElementCriteria $criteria
	 * @return Element
	 */
	public function element(\PHPUnit_Extensions_Selenium2TestCase_ElementCriteria $criteria)
	{
		$value = $this->postCommand('element', $criteria);
		return static::fromResponseValue($value, $this->url->ascend(), $this->driver);
	}

	/**
	 * Finds elements in the scope of this element.
	 *
	 * @param \PHPUnit_Extensions_Selenium2TestCase_ElementCriteria $criteria
	 * @return Element[] array of instances of Se34\Element
	 */
	public function elements(\PHPUnit_Extensions_Selenium2TestCase_ElementCriteria $criteria)
	{
		$values = $this->postCommand('elements', $criteria);
		$elements = array();
		foreach ($values as $value)
		{
			$elements[] = static::fromResponseValue($value, $this->url->ascend(), $this->driver);
		}
		return $elements;
	}

	/**
	 * Find the first element that matches the criteria and return it. Or throw
	 * an exception.
	 *
	 * @param string $strategy
	 * @param string $selector
	 * @return Element
	 */
	public function findElement($strategy, $selector)
	{
		return $this->element($this->using($strategy)->value($selector));
	}

	/**
	 * Find all elements that match given criteria. If none found, than return
	 * an empty array.
	 *
	 * @param string $strategy
	 * @param string $selector
	 * @return Element[]
	 */
	public function findElements($strategy, $selector)
	{
		return $this->elements($this->using($strategy)->value($selector));
	}
}
