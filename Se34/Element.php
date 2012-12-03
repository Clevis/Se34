<?php

namespace Se34;

/**
 * Objekt reprezentující DOM prvek.
 *
 * Slouží hlavně k tomu, abych nemusel psát šílený název třídy `PHPUnit_Extensions_Selenium2TestCase_Element`.
 *
 * @author Václav Šír
 */
class Element extends \PHPUnit_Extensions_Selenium2TestCase_Element
{

	/**
	 * Vytvoří objekt elementu z hodnoty, kterou vrátil Selenium driver.
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
	 * Hledá element uvnitř tohoto elementu.
	 * @return Element
	 */
	public function element(\PHPUnit_Extensions_Selenium2TestCase_ElementCriteria $criteria)
	{
		$value = $this->postCommand('element', $criteria);
		return static::fromResponseValue($value, $this->url->ascend(), $this->driver);
	}

	/**
	 * Hledá elementy uvnitř tohoto elementu.
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

}
