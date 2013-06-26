<?php

namespace Se34;

use Nette\ObjectMixin;

/**
 * Základní třída pro seleniové testy.
 *
 * @property-read \Nette\DI\Container|\SystemContainer $context
 * @property-read BrowserSession $session Aktuální browser session.
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
	 * Vytvoří systémový DI kontejner.
	 *
	 * Je nutné, aby potomek v této metodě vytvořil DI container, který
	 * poskytuje tyto parametry:
	 *
	 * <pre>
	 * selenium:
	 *   baseUrl: http://localhost/projekt/www/ # Adresa rootu webu
	 *   seleniumServer: http://localhost:4444 # Adresa Selenium Serveru
	 *   desiredCapabilities:
	 *     browserName: firefox # Prohlížeč, který má Selenium použít
	 * </pre>
	 *
	 * Kontejner také musí dodávat službu typu {@see \Nette\Application\IRouter}
	 * s názvem "router", jinak dost věcí nebude fungovat.
	 *
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
	 * Vytvoří session (otevře prohlížeč).
	 * @return BrowserSession
	 */
	protected function createSession()
	{
		return new BrowserSession($this->getContext());
	}

	/**
	 * Vrátí aktuální browser session.
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
	 * Ověří, že aktuální URL míří na požadovaný presenter.
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
	 * Ověří název elementu.
	 * @param string $expectedTagName
	 * @param Element $element
	 */
	public function assertTagName($expectedTagName, Element $element)
	{
		$this->assertSame($expectedTagName, $actualTagName = $element->name(), __FUNCTION__ . ": Byl očekáván element '$expectedTagName', je '$actualTagName'.");
	}

	/**
	 * Ověří hodnoty atributů elementu (ověřují se pouze vyjmenované atributy).
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
	 * Ověří shodnost dvou elementů v DOMu.
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
		return ObjectMixin::set($this, $name, $value);
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
