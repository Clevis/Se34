<?php

namespace Se34;

use Nette\Object;
use Nette\Reflection\ClassType;

/**
 * Base class for objects representing pages.
 *
 * @property-read BrowserSession $parent
 * @author Václav Šír
 */
abstract class PageObject extends PageComponent implements IPageObject
{

	/**
	 *
	 * @var string
	 */
	protected $presenterName;

	/**
	 *
	 * @var array|string
	 */
	protected $presenterParameters = array();

	/**
	 *
	 * @var string
	 */
	protected $url;

	public function __construct(BrowserSession $session, IPageComponent $parent = NULL, array $parameters = NULL)
	{
		if ($parent !== NULL)
		{
			throw new \InvalidArgumentException('PageObject must not have a $parent.'); // todo Se34\InvalidArgumentException
		}
		$this->parseNavigationAnnotations();
		parent::__construct($session, $this, $parameters);
	}

	/**
	 * Navigates to this page.
	 *
	 * If `$this->url` is set, it navigates straight to this URL. Otherwise
	 * it navigates to the presenter from `$this->presenterName` with parameters
	 * from `$this->parameters`.
	 *
	 * @return PageObject $this
	 */
	public function navigate()
	{
		if ($this->url)
		{
			$this->session->url($this->url);
		}
		else
		{
			$this->session->navigate($this->presenterName, $this->presenterParameters);
		}
		return $this;
	}

	/**
	 * Checks that this page is open in the browser.
	 *
	 * Default implementaion checks current URL against either `$this->url`
	 * or `$this->presenterName` and `$this->parameters`.
	 *
	 * When testing some brually ajaxified application, this might be useful
	 * to redefine in descendants.
	 *
	 * It is performed by each access to an element through a shortcut.
	 *
	 * @throws ViewStateException
	 */
	public function checkState()
	{
		if ($this->url)
		{
			if (($actualUrl = $this->session->url()) !== $this->url)
			{
				throw new ViewStateException(__METHOD__ . ": URL '$this->url' was expected, actual URL is '$actualUrl'.");
			}
		}
		else
		{
			$appRequest = $this->session->appRequest;
			if ($appRequest->presenterName !== $this->presenterName)
			{
				throw new ViewStateException(__METHOD__ . ": Presenter '$this->presenterName' was expected, actual presenter is '$appRequest->presenterName'.");
			}
			foreach (Utils::strToArray($this->presenterParameters) as $name => $value)
			{
				if ($appRequest->parameters[$name] !== $value)
				{
					throw new ViewStateException(__METHOD__ . ": Parameter '$name' is expected to be '$value', but is '{$appRequest->parameters[$name]}'.");
				}
			}
		}
	}

	public function findElement($strategy, $selector)
	{
		return $this->session->findElement($strategy, $selector);
	}

	public function findElements($strategy, $selector)
	{
		return $this->session->findElements($strategy, $selector);
	}

	private function parseNavigationAnnotations()
	{
		foreach (Utils::getWholeLineageOfClass(get_class($this)) as $className)
		{
			$classAnnotations = Utils::getClassAnnotations($className);
			if (isset($classAnnotations['presenterName']))
			{
				$this->presenterName = reset($classAnnotations['presenterName']);
			}
			if (isset($classAnnotations['presenterParameters']))
			{
				$this->presenterParameters = reset($classAnnotations['presenterParameters']);
			}
			if (isset($classAnnotations['url']))
			{
				$this->url = reset($classAnnotations['url']);
			}
		}
	}

}
