<?php

namespace Se34;

use Nette\ObjectMixin;

/**
 * Base class for Selenium WebDriver based tests.
 *
 * @property-read \Nette\DI\Container|\SystemContainer $context
 * @property-read BrowserSession $session Current browser session.
 * @author Václav Šír
 */
abstract class TestCase extends \PHPUnit_Framework_TestCase
{

	/**
	 * @var \Nette\DI\Container|\SystemContainer
	 */
	private $context;

	/**
	 * @var BrowserSession
	 */
	private $session;

	/**
	 * @var bool
	 */
	protected $keepOpenOnFailure = TRUE;

	/**
	 * Creates system DI container.
	 *
	 * It is mandatory, that the descendant creates a DI container, that has
	 * these parameters:
	 *
	 * <pre>
	 * selenium:
	 *   baseUrl: http://localhost/projekt/www/ # Adress of the root of the webu
	 *   seleniumServer: http://localhost:4444 # Adress of the Selenium Server
	 *   desiredCapabilities:
	 *     browserName: firefox # Browser to use
	 * </pre>
	 *
	 * The container also relies on a service of type {@see \Nette\Application\Router}
	 * with name "router", some thangs won't work otherwise.
	 *
	 * @todo Make BrowserSession a DIC service and inject router by its type.
	 * @return \Nette\DI\Container
	 */
	abstract protected function createContext();

	/**
	 * @return \Nette\DI\Container|\SystemContainer
	 */
	public function getContext()
	{
		if (!$this->context)
		{
			$this->context = $this->createContext();
		}
		return $this->context;
	}

	/**
	 * Cleates a new session (opens a new browser window).
	 * @return BrowserSession
	 */
	protected function createSession()
	{
		return new BrowserSession($this->getContext());
	}

	/**
	 * Returns current browser session.
	 *
	 * @return BrowserSession
	 */
	public function getSession()
	{
		if (!$this->session)
		{
			$this->session = $this->createSession();
		}
		return $this->session;
	}

	/**
	 * Closes the session. Keeps the session open on failure.
	 *
	 * Note that the session closes itself anyway after while. But if you're
	 * close to the computer that runs the test, it may help you to see what
	 * exactly happened on the gorram tested page.
	 */
	protected function tearDown()
	{
		parent::tearDown();
		if ($this->session && $this->keepOpenOnFailure && $this->getStatus() !== \PHPUnit_Runner_BaseTestRunner::STATUS_PASSED)
		{
			$this->session->keepOpen();
		}
		if ($this->session)
		{
			$this->session->stop();
		}
	}

	/**
	 * Checks that the current URL points to the desired presenter.
	 *
	 * @param string $presenterName
	 * @param array|string $parameters
	 */
	public function assertPresenter($presenterName, $parameters = array())
	{
		$appRequest = $this->session->appRequest;
		$this->assertSame($presenterName, $appRequest->presenterName, __FUNCTION__ . ": Presenter by měl být '$presenterName', je '$appRequest->presenterName'.");
		foreach (Utils::strToArray($parameters) as $key => $value)
		{
			$this->assertSame($value, $appRequest->parameters[$key], __FUNCTION__ . ": Parametr $key by měl být '$value', je '{$appRequest->parameters[$key]}'.");
		}
	}

	/**
	 * Checks the element tag name.
	 *
	 * @param string $expectedTagName
	 * @param Element $element
	 */
	public function assertTagName($expectedTagName, Element $element)
	{
		$this->assertSame($expectedTagName, $actualTagName = $element->name(), __FUNCTION__ . ": Byl očekáván element '$expectedTagName', je '$actualTagName'.");
	}

	/**
	 * Checks attributes of an element (only listed attributes are checked).
	 *
	 * @param array|string $expectedAttributes
	 * @param Element $element
	 */
	public function assertTagAttributes($expectedAttributes, Element $element)
	{
		foreach (Utils::strToArray($expectedAttributes) as $attributeName => $expectedValue)
		{
			$this->assertSame($expectedValue, $actualValue = $element->attribute($attributeName), __FUNCTION__ . ": Atribut '$attributeName' by měl být '$expectedValue', je '$actualValue'.");
		}
	}

	/**
	 * Checks identity of two DOM elements.
	 *
	 * @param Element $element
	 * @param Element $other
	 */
	public function assertElementEquals(Element $element, Element $other)
	{
		if (!$element->equals($other))
		{
			$message = __FUNCTION__ . ': Element id=' . $element->getId()
				. ' (tag = "' . $element->name() . '"' . (($htmlId = $element->attribute('id')) !== '' ? ", html id=\"$htmlId\"" : '') . ')'
				. ' does not equal to element id=' . $other->getId()
				. ' (tag = "' . $other->name() . '"' . (($htmlId = $other->attribute('id')) !== '' ? ", html id=\"$htmlId\"" : '') . ')'
				. '.';
			$this->fail($message);
		}
		else
		{
			$this->assertTrue(TRUE);
		}
	}

	/** @see Nette\ObjectMixin */
	public function &__get($name)
	{
		return ObjectMixin::get($this, $name);
	}

	/** @see Nette\ObjectMixin */
	public function __set($name, $value)
	{
		ObjectMixin::set($this, $name, $value);
	}

	/** @see Nette\ObjectMixin */
	public function __isset($name)
	{
		return ObjectMixin::has($this, $name);
	}

	/** @see Nette\ObjectMixin */
	public function __unset($name)
	{
		ObjectMixin::remove($this, $name);
	}

}
