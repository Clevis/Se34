<?php

namespace Se34;

/**
 * @author Václav Šír
 */
interface IPageObject extends IPageComponent
{
	/**
	 * Checks that this page is open in browser.
	 * @throws ViewStateException
	 */
	public function checkState();
}
