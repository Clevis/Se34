<?php

namespace Se34;

use Nette\ObjectMixin;

/**
 * Browser session.
 *
 * @property-read \Nette\Http\UrlScript $urlScript Current URL as an UrlScript object.
 * @property-read \Nette\Application\Request $appRequest Current URL back-routed into application request object.
 * @property-read Element $activeElement Get the element on the page that currently has focus.
 * @method Element byClassName($value) Find an element by class name. Compound class names are not permitted.
 * @method Element byCssSelector($value) Find an element by a CSS selector.
 * @method Element byId($value) Find an element by ID.
 * @method Element byName($value) Find an element by name attribute.
 * @method Element byXPath($value) Find an element by XPath query.
 * @method Element byLinkText($value) Find an anchor element of  by its visible text.
 * @method NULL moveto(Element $element) Moves imaginary mouse pointer over an element.
 * @method NULL buttondown($jsonParameters = NULL) Click and hold the left mouse button (at the coordinates set by the last moveto command).
 * @method NULL buttonup($jsonParameters = NULL) Releases the mouse button previously held (where the mouse is currently at). Must be called once for every buttondown command issued.
 * @method string screenshot() Take a screenshot of the current page. Returns a base64 encoded PNG image.
 * @method NULL touchUp($jsonParameters = NULL) Finger up on the screen. JSON Parameters: x, y.
 * @method NULL touchDown($jsonParameters = NULL) Finger up on the screen. JSON Parameters: x, y.
 * @method NULL touchMove($jsonParameters = NULL) Finger move on the screen. JSON Parameters: x, y.
 * @method NULL touchScroll($jsonParameters = NULL) Scroll on the touch screen using finger based motion events. JSON Parameters: element, xoffset, yoffset.
 * @method NULL flick($jsonParameters = NULL) Flick on the touch screen using finger motion events. JSON Parameters: element, xoffset, yoffset, speed.
 * @method array|NULL location($jsonParameters = NULL) Get/set the current geo location. JSON Parameters: { latitude: number, longitude: number, altitude: number }
 * @method string orientation() Get the current browser orientation (LANDSCAPE or PORTRAIT).
 * @author Václav Šír
 */
class BrowserSession extends \PHPUnit_Extensions_Selenium2TestCase_Session
{

	/**
	 * DI container.
	 * @param \Nette\DI\Container|\SystemContainer $context
	 */
	private $context;

	/**
	 * You can turn of Bluescreen detection using this variable.
	 * @var bool
	 */
	public $checkForBlueScreen = TRUE;

	/**
	 * List of commands, that are excluded from the Bluescreen detection.
	 * @var array
	 */
	public $commandsWithoutCheckForBluescreen = array(
		'alertText',
		'acceptAlert',
		'dismissAlert',
	);

	/**
	 * ID of an element, by whose presence is Bluescreen detected and its content contains description of the error.
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

	/**
	 * List of all supported commands.
	 *
	 * Keys are commands, value is either a class name or a closure.
	 *
	 * If you want to add some new WebDriver commands, this is the place.
	 * @return array
	 */
	protected function initCommands()
	{
		$commands = parent::initCommands() + array(
				'doubleclick' => 'PHPUnit_Extensions_Selenium2TestCase_ElementCommand_GenericPost',
			);
		return $commands;
	}

	/**
	 * Calling this method avoids destructor to send DELETE session request.
	 *
	 * The browser window will close either way, but after some quite long
	 * timeout (at least with ChromeDriver).
	 */
	public function keepOpen()
	{
		$stoppedProperty = new \ReflectionProperty(get_parent_class($this), 'stopped');
		$stoppedProperty->setAccessible(TRUE);
		$stoppedProperty->setValue($this, TRUE);
	}

	/**
	 * Current URL as an UrlScript object.
	 * @return \Nette\Http\UrlScript
	 */
	public function getUrlScript()
	{
		return $this->getUrlScriptForUrl($this->url());
	}

	/**
	 * @param $url
	 * @return \Nette\Http\UrlScript
	 */
	private function getUrlScriptForUrl($url)
	{
		$baseUrl = new \Nette\Http\Url($this->context->parameters['selenium']['baseUrl']);
		$urlScript = new \Nette\Http\UrlScript($url);
		$urlScript->scriptPath = $baseUrl->path;
		return $urlScript;
	}

	/**
	 * Creates an URL.
	 *
	 * Parameters can be either a PHP array, or a Neon array without brackets.
	 * Ie. Instead of `array('a' => 'b', 'c' => 'd')` you can pass `'a=b,c=d'`.
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
	 * URL back-routed into application request object.
	 *
	 * @param string|NULL $url URL or NULL for current URL.
	 * @return \Nette\Application\Request
	 */
	public function getAppRequest($url = NULL)
	{
		$httpRequest = new \Nette\Http\Request($this->getUrlScriptForUrl($url ? : $this->url()));
		return $this->context->router->match($httpRequest);
	}

	/**
	 * Wait for javascript alert, prompt or confirm dialog.
	 *
	 * @param int $timeout Patience in seconds.
	 * @return string|bool Alert text, or FALSE if waiting times out.
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
	 * Wait for document is fully loaded (document.readyState).
	 *
	 * @param int $timeout
	 * @return bool
	 */
	public function waitForDocument($timeout = 60)
	{
		return $this->waitForCondition('document.readyState == "complete"', $timeout);
	}

	/**
	 * Wait for finishing of jQuery AJAX query.
	 *
	 * @param int $timeout
	 * @return bool
	 */
	public function waitForAjax($timeout = 60)
	{
		return $this->waitForCondition('jQuery.active == 0', $timeout);
	}

	/**
	 * Wait for fulfilment of some javascript condition.
	 *
	 * @param string $jsCondition Javascript code.
	 * @param int $timeout Amount of patience in seconds.
	 * @return bool Whether the condition was fulfilled.
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
	 * Navigate to given presenter.
	 *
	 * @param string $presenterName
	 * @param array|string $parameters
	 */
	public function navigate($presenterName, $parameters = array())
	{
		$this->url($this->getLink($presenterName, $parameters));
	}

	/**
	 * Get the element on the page that currently has focus.
	 *
	 * @return Element
	 */
	public function getActiveElement()
	{
		$response = $this->driver->curl('POST', $this->url->addCommand('element/active'));
		return Element::fromResponseValue($response->getValue(), $this->url->descend('element'), $this->driver);
	}

	/**
	 * Finds an element using the criteria.
	 *
	 * Criteria are set by this WTF way:
	 * <code>
	 * $session->element($session->using('xpath')->value('//input[type="text"]'));
	 * </code>
	 *
	 * @param \PHPUnit_Extensions_Selenium2TestCase_ElementCriteria $criteria
	 * @return Element
	 */
	public function element(\PHPUnit_Extensions_Selenium2TestCase_ElementCriteria $criteria)
	{
		$value = $this->postCommand('element', $criteria);
		return Element::fromResponseValue($value, $this->url->descend('element'), $this->driver);
	}

	/**
	 * Finds elements using given criteria.
	 *
	 * Similar to {@see BrowserSession::element()}, only this returns all matched
	 * elements as an array. Also, this doesn't throw an exception if no
	 * element was found.
	 *
	 * @param \PHPUnit_Extensions_Selenium2TestCase_ElementCriteria $criteria
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
	 * Executes a WebDriver command and checks if the Nette Bluescreen (aka
	 * Tracy or Laděnka) has appeared.
	 *
	 * Presence of the bluescreen is recognized by a javascript check of presence
	 * of an element with ID `$this->blueScreenId`.
	 *
	 * You may suppress this detection globaly (`$this->checkForBlueScreen`),
	 * or for some particular commands (`$this->commandsWithoutCheckForBluescreen`)
	 * and also it isn't performed for `$this->url()` (without parameters)
	 * and `$this->byId($this->blueScreenId)` to avoid recursion.
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
