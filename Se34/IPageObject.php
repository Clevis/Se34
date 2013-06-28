<?php

namespace Se34;

/**
 * @author Václav Šír
 */
interface IPageObject
{

	public function __construct(BrowserSession $session);

	/**
	 * Checks that this page is open in browser.
	 * @throws ViewStateException
	 */
	public function checkState();
}
