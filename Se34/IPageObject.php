<?php

namespace Se34;

/**
 * @author Václav Šír
 */
interface IPageObject
{

	public function __construct(BrowserSession $session);

	/**
	 * Ověří, že je v prohlížeči otevřená tato stránka.
	 * @throws ViewStateException
	 */
	public function checkState();
}
