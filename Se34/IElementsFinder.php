<?php

namespace Se34;

interface IElementsFinder
{

	/**
	 * Find the first element that matches the criteria and return it. Or throw
	 * an exception.
	 *
	 * @param string $strategy
	 * @param string $selector
	 * @return Element
	 */
	public function findElement($strategy, $selector);

	/**
	 * Find all elements that match given criteria. If none found, than return
	 * an empty array.
	 *
	 * @param string $strategy
	 * @param string $selector
	 * @return Element[]
	 */
	public function findElements($strategy, $selector);

}
