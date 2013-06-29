<?php

namespace Se34;

interface IPageComponent extends IElementsFinder
{

	public function __construct(BrowserSession $session, IPageComponent $parent = NULL, array $parameters = array());

	/**
	 * Checks that this page is open in browser.
	 * @throws ViewStateException
	 */
	public function checkState();

}
