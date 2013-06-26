<?php

namespace Se34;

use Nette\ObjectMixin;

/**
 * Browser session.
 *
 * @property-read \Nette\Http\UrlScript $urlScript Aktuální URL jako objekt.
 * @property-read \Nette\Application\Request $appRequest Výsledek zpětného routování aktuálního URL.
 * @property-read Element $activeElement Get the element on the page that currently has focus.
 * @method Element byClassName($value)
 * @method Element byCssSelector($value)
 * @method Element byId($value)
 * @method Element byName($value)
 * @method Element byXPath($value)
 * @method Element byLinkText($value)
 * @method NULL moveto(Element $element) Přesune pomyslný ukazatel myši nad element.
 * @method NULL buttondown($jsonParameters = NULL) Click and hold the left mouse button (at the coordinates set by the last moveto command).
 * @method NULL buttonup($jsonParameters = NULL) Releases the mouse button previously held (where the mouse is currently at). Must be called once for every buttondown command issued.
 * @method string screenshot() Take a screenshot of the current page. The screenshot as a base64 encoded PNG.
 * @method NULL touchUp($jsonParameters = NULL) Finger up on the screen. JSON Parameters: x, y.
 * @method NULL touchDown($jsonParameters = NULL) Finger up on the screen. JSON Parameters: x, y.
 * @method NULL touchMove($jsonParameters = NULL) Finger move on the screen. JSON Parameters: x, y.
 * @method NULL touchScroll($jsonParameters = NULL) Scroll on the touch screen using finger based motion events. JSON Parameters: element, xoffset, yoffset.
 * @method NULL flick($jsonParameters = NULL) Flick on the touch screen using finger motion events. JSON Parameters: element, xoffset, yoffset, speed.
 * @method array|NULL location($jsonParameters = NULL) Get/set the current geo location. JSON Parameters: location -
{
latitude: number, longitude: number, altitude: number
}
 * @method string orientation() Get the current browser orientation.
 * @author Václav Šír
 */
class BrowserSession extends \PHPUnit_Extensions_Selenium2TestCase_Session
{

	/**
	 * @param \Nette\DI\Container|\SystemContainer $context
	 */
	private $context;

	/**
	 * Touto proměnnou lze vypnout hledání Bluescreenu.
	 * @var bool
	 */
	public $checkForBlueScreen = TRUE;

	/**
	 * Seznam příkazů, které jsou vyjmuté z kontroly na Bluescreen.
	 * @var array
	 */
	public $commandsWithoutCheckForBluescreen = array(
		'alertText',
		'acceptAlert',
		'dismissAlert',
	);

	/**
	 * ID prvku, jehož přítomnost indikuje Bluescreen a jehož obsah je popisem chyby.
	 * @var string
	 */
	public $blueScreenId = 'netteBluescreenError';

	/**
	 * @param \Nette\DI\Container $context
	 */
	public function __construct(\Nette\DI\Container $context)
	{
		$this->context = $context;

		$seleniumServerUrl = new \PHPUnit_Extensions_Selenium2TestCase_URL($context->parameters['selenium']['seleniumServer']);
		$driver = new \PHPUnit_Extensions_Selenium2TestCase_Driver($seleniumServerUrl);
		$sessionCreationApiUrl = $seleniumServerUrl->descend("/wd/hub/session");
		$sessionCreationResponse = $driver->curl('POST', $sessionCreationApiUrl, array(
			'desiredCapabilities' => $context->parameters['selenium']['desiredCapabilities']
		));
		$sessionApiUrl = $sessionCreationResponse->getUrl();
		$baseUrl = new \PHPUnit_Extensions_Selenium2TestCase_URL($context->parameters['selenium']['baseUrl']);
		$timeouts = new \PHPUnit_Extensions_Selenium2TestCase_Session_Timeouts(
			$driver,
			$sessionApiUrl->descend('timeouts'),
			60 * 1000
		);

		parent::__construct($driver, $sessionApiUrl, $baseUrl, $timeouts);
	}

	protected function initCommands()
	{
		$commands = parent::initCommands() + array(
				'doubleclick' => 'PHPUnit_Extensions_Selenium2TestCase_ElementCommand_GenericPost',
			);
		return $commands;
	}

	/**
	 * Volání této funkce zabrání destruktoru, aby poslal Seleniu příkaz k zavření session.
	 */
	public function keepOpen()
	{
		$stoppedProperty = new \ReflectionProperty(get_parent_class($this), 'stopped');
		$stoppedProperty->setAccessible(TRUE);
		$stoppedProperty->setValue($this, TRUE);
	}

	/**
	 * Vrátí URL jako UrlScript objekt.
	 * @return \Nette\Http\UrlScript
	 */
	private function getUrlScript($url)
	{
		$baseUrl = new \Nette\Http\Url($this->context->parameters['selenium']['baseUrl']);
		$urlScript = new \Nette\Http\UrlScript($url);
		$urlScript->scriptPath = $baseUrl->path;
		return $urlScript;
	}

	/**
	 * Vytvoří odkaz.
	 *
	 * Parametry můžou být buďto PHP pole, nebo Neon pole bez závorek (tzn.
	 * kromě zápisu `array('a' => 'b', 'c' => 'd')` lze použít i `'a=b,c=d'`).
	 *
	 * @param string $presenterName
	 * @param array|string $parameters
	 */
	public function getLink($presenterName, $parameters = array())
	{
		$url = new \Nette\Http\UrlScript($this->context->parameters['selenium']['baseUrl']);
		$url->scriptPath = $url->path;
		$appRequest = new \Nette\Application\Request($presenterName, 'GET', Utils::strToArray($parameters));
		return $this->context->router->constructUrl($appRequest, $url);
	}

	/**
	 * Prožene URL routerem a vrátí výsledek.
	 * @param string $url NULL = aktuální URL.
	 * @return \Nette\Application\Request
	 */
	public function getAppRequest($url = NULL)
	{
		$httpRequest = new \Nette\Http\Request($this->getUrlScript($url ? : $this->url()));
		return $this->context->router->match($httpRequest);
	}

	/**
	 * Počká na javascriptový alert, prompt nebo confirm.
	 *
	 * @param int $timeout Trpělivost v sekundách.
	 * @return string|bool Text alertu, nebo FALSE (= alertu nedočkáno).
	 */
	public function waitForAlert($timeout = 60)
	{
		$result = FALSE;
		$i = 0;
		do
		{
			sleep(1);
			try
			{
				$result = $this->alertText();
			}
			catch (\RuntimeException $e)
			{
				;
			}
		} while (++$i < $timeout && $result === FALSE);
		return $result;
	}

	/**
	 * Počkat na načtení dokumentu (document.readyState).
	 *
	 * @param int $timeout
	 * @return bool
	 */
	public function waitForDocument($timeout = 60)
	{
		return $this->waitForCondition('document.readyState == "complete"', $timeout);
	}

	/**
	 * Počkat na dokončení jQuery AJAX požadavku.
	 *
	 * @param int $timeout
	 * @return bool
	 */
	public function waitForAjax($timeout = 60)
	{
		return $this->waitForCondition('jQuery.active == 0', $timeout);
	}

	/**
	 * Počkat na splnění javascriptové podmínky.
	 *
	 * @param string $jsCondition Javascriptový kód.
	 * @param int $timeout Množství trpělivosti v sekundách.
	 * @return bool Jestli jsme se dočkali.
	 */
	public function waitForCondition($jsCondition, $timeout = 60)
	{
		$i = 0;
		do
		{
			sleep(1);
		} while (
			!($result = $this->execute(array('script' => 'return ' . $jsCondition, 'args' => array())))
			&& $i++ < $timeout
		);
		return $result;
	}

	/**
	 * Přejde na určený presenter.
	 * @param string $presenterName
	 * @param array|string $parameters
	 */
	public function navigate($presenterName, $parameters = array())
	{
		$this->url($this->getLink($presenterName, $parameters));
	}

	/**
	 * Get the element on the page that currently has focus.
	 * @return Element
	 */
	public function getActiveElement()
	{
		$response = $this->driver->curl('POST', $this->url->addCommand('element/active'));
		return Element::fromResponseValue($response->getValue(), $this->url->descend('element'), $this->driver);
	}

	/**
	 * Najde element podle zadaných kritérií.
	 *
	 * Kritéria se zadávají tímto mírně WTF způsobem:
	 * <code>
	 * $session->element($session->using('xpath')->value('//input[type="text"]'));
	 * </code>
	 * @return Element
	 */
	public function element(\PHPUnit_Extensions_Selenium2TestCase_ElementCriteria $criteria)
	{
		$value = $this->postCommand('element', $criteria);
		return Element::fromResponseValue($value, $this->url->descend('element'), $this->driver);
	}

	/**
	 * Najde elementy podle zadaných kritérií.
	 *
	 * Totéž, co {@see BrowserSession::element()}, jenom vrací všechny vyhovující
	 * elementy jako pole.
	 *
	 * @return Element[] array of instances of Se34\Element
	 */
	public function elements(\PHPUnit_Extensions_Selenium2TestCase_ElementCriteria $criteria)
	{
		$values = $this->postCommand('elements', $criteria);
		$elements = array();
		foreach ($values as $value)
		{
			$elements[] = Element::fromResponseValue($value, $this->url->descend('element'), $this->driver);
		}
		return $elements;
	}

	/**
	 * Vykoná seleniový příkaz a zkontroluje, jestli na stránce není Nette Bluescreen.
	 *
	 * Přítomnost bluescreenu se zjišťuje javascriptovou kontrolou na přítomnost
	 * elementu s ID `$this->blueScreenId`.
	 *
	 * Tuto kontrolu lze zakázat plošně (`$this->checkForBlueScreen`), nebo pro
	 * určité příkazy (`$this->commandsWithoutCheckForBluescreen`) a také se neprovádí
	 * pro `$this->url()` (bez parametrů) a `$this->byId($this->blueScreenId)`
	 * kvůli rekurzi.
	 *
	 * @param string $command
	 * @param array $arguments
	 * @return mixed
	 */
	public function __call($command, $arguments)
	{
		$result = parent::__call($command, $arguments);

		if ($this->checkForBlueScreen)
		{
			$checkExecuteArgs = array(
				'script' => 'return document.getElementById(' . json_encode($this->blueScreenId) . ') != undefined',
				'args' => array()
			);
			$funcGetArgs = func_get_args();
			if (
				$funcGetArgs !== array('execute', array($checkExecuteArgs))
				&& $funcGetArgs !== array('byId', array($this->blueScreenId))
				&& $funcGetArgs !== array('url', array())
				&& !in_array($command, $this->commandsWithoutCheckForBluescreen)
				&& $this->execute($checkExecuteArgs)
			)
			{
				$text = $this->byId($this->blueScreenId)->text();
				throw new BluescreenException("Na stránce:\n" . $this->url() . "\n se vyskytla chyba:\n" . $text);
			}
		}
		return $result;
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
