<?php

namespace Se34;

class ElementComponent extends PageComponent
{

	/** @var Element */
	private $root;

	/**
	 * Get root element of this component.
	 * @return Element
	 */
	private function getRoot()
	{
		if ($this->root === NULL)
		{
			$selector = reset($this->parameters);
			$strategy = key($this->parameters);
			if ($selector instanceof Element)
			{
				$this->root = $selector;
			}
			else
			{
				$this->root = $this->parent->findElement($strategy, $selector);
			}
			$expectedTagName = next($this->parameters);
			$expectedAttributes = next($this->parameters);
			if ($expectedTagName !== FALSE)
			{
				$actualTagName = $this->root->name();
				if ($actualTagName !== $expectedTagName)
				{
					throw new ViewStateException("Root element of '" . get_class($this) . "' is expected to be tag '$expectedTagName', but is '$actualTagName'.");
				}
			}
			if ($expectedAttributes !== FALSE)
			{
				foreach ($expectedAttributes as $attributeName => $expectedAttributeValue)
				{
					$actualAttributeValue = $this->root->attribute($attributeName);
					if ($actualAttributeValue !== $expectedAttributeValue)
					{
						throw new ViewStateException("Root element's attribute '$attributeName' is expected to be '$expectedAttributeValue', but is '$actualAttributeValue'.");
					}
				}
			}
		}
		return $this->root;
	}

	/**
	 * Override this method to provide some custom selectors.
	 *
	 * @param $strategy
	 * @param $selector
	 */
	protected function modifyCriteria(&$strategy, &$selector)
	{
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
		$this->modifyCriteria($strategy, $selector);
		return $this->getRoot()->findElement($strategy, $selector);
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
		$this->modifyCriteria($strategy, $selector);
		return $this->getRoot()->findElements($strategy, $selector);
	}

}
