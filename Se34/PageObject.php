<?php

namespace Se34;

use Nette\Object;
use Nette\Reflection\ClassType;

/**
 * Základní třída pro objekty představující stránky.
 *
 * @property-read BrowserSession $session
 * @author Václav Šír
 */
abstract class PageObject extends Object implements IPageObject
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
	protected $parameters = array();

	/**
	 *
	 * @var string
	 */
	protected $url;

	/**
	 * @var BrowserSession
	 */
	private $session;

	/**
	 * Zpracované zkratky z anotací property-read. Nepřistupovat přímo, získávat
	 * přes `$this->getShortcuts()`.
	 *
	 * <code>
	 * array(
	 * 	// @property-read Element $propertyName strategy=value, expectedTagName, (attrib = value)
	 * 	'propertyName' => array(
	 * 		'strategy',
	 * 		'value',
	 * 		FALSE, // is array of elements
	 * 		'expectedTagName',
	 * 		array('attrib' => 'value'),
	 * 	),
	 * );
	 * </code>
	 * @var array
	 */
	private $shortcuts;

	/**
	 * Zpracované `@method` anotace.
	 *
	 * <code>
	 * array(
	 * 	// @method Foo clickBar()
	 * 	'clickBar' => array(
	 * 		'bar',       // název zkratky
	 * 		'click',     // název metody volané na zkratce
	 * 		'Foo',       // název třídy dalšího page objektu
	 * 		'SomeClass', // definující třída (kvůli namespacu)
	 * 	)
	 * );
	 * </code>
	 * @var array
	 */
	private $methods;

	/**
	 * @param BrowserSession $session
	 */
	public function __construct(BrowserSession $session)
	{
		$this->session = $session;
	}

	/**
	 * Přejde na tuto stránku.
	 *
	 * Pokud je nastavené `$this->url`, tak přejde na toto URL. Jinak na
	 * presenter podle `$this->presenterName` + `$this->parameters`.
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
			$this->session->navigate($this->presenterName, $this->parameters);
		}
		return $this;
	}

	/**
	 * Ověří, že je v prohlížeči otevřená tato stránka.
	 *
	 * Výchozí implementace to dělá podle aktuální URL. Pokud je nastaveno
	 * `$this->url`, tak přímo podle této hodnoty. Jinak porovnává aktuální
	 * presenter ({@see BrowserSession::getAppRequest()}) a parametry proti
	 * `$this->presenterName` + `$this->parameters`.
	 *
	 * Pokud je testovaná aplikace nějak brutálně zajaxovaná, může být záhodno
	 * toto chování v potomcích změnit.
	 *
	 * Provádí se při každém přístupu k prvku přes zkratku, tedy i při volání
	 * magických metod.
	 *
	 * @throws ViewStateException
	 */
	public function checkState()
	{
		if ($this->url)
		{
			if (($actualUrl = $this->session->url()) !== $this->url)
			{
				throw new ViewStateException(__METHOD__ . ": Očekávána URL '$this->url', je '$actualUrl'.");
			}
		}
		else
		{
			$appRequest = $this->session->appRequest;
			if ($appRequest->presenterName !== $this->presenterName)
			{
				throw new ViewStateException(__METHOD__ . ": Očekáván presenter '$this->presenterName', je '$appRequest->presenterName'.");
			}
			foreach (Utils::strToArray($this->parameters) as $name => $value)
			{
				if ($appRequest->parameters[$name] !== $value)
				{
					throw new ViewStateException(__METHOD__ . ": Parametr '$name' je očekáván '$value', je '{$appRequest->parameters[$name]}'.");
				}
			}
		}
	}

	/**
	 * @return BrowserSession
	 */
	public function getSession()
	{
		return $this->session;
	}

	/**
	 * Pro každou položku zavolá plnící metodu.
	 *
	 * <code>
	 * $page->fill(array(
	 * 	'foo' => 'bar',
	 * 	'e' => 'mc^2',
	 * ));
	 * // Provede tohle:
	 * $page->fillFoo('bar')
	 * $page->fillE('mc^2');
	 * </code>
	 * @param array $values
	 * @return PageObject
	 */
	public function fill(array $values)
	{
		foreach ($values as $key => $value)
		{
			callback($this, 'fill' . ucfirst($key))->invoke($value);
		}
		return $this;
	}

	/**
	 * Získá názvy všech tříd, jichž je objekt instancí.
	 * @return array
	 */
	private function getThisClasses()
	{
		$lineage = array(get_class($this));
		while (($lineage[] = get_parent_class(end($lineage))) !== FALSE);
		array_pop($lineage);
		rsort($lineage);
		return $lineage;
	}

	/**
	 * Dodává zpracované definice zkratek k elementům (anotace `@property-read`).
	 * @return array
	 */
	private function getShortcuts()
	{
		if (!isset($this->shortcuts))
		{
			foreach ($this->getThisClasses() as $className)
			{
				$annotations = Utils::getClassAnnotations($className);
				$readOnlyProperties = isset($annotations['property-read']) ? $annotations['property-read'] : array();
				foreach ($readOnlyProperties as $property)
				{
					// Rozkouskování @property-read $propertyType $propertyName $propertyDescription
					list($propertyType, $propertyName, $propertyDescription) = preg_split('~\s+~', $property, 3) + array(NULL, NULL, NULL);
					if (substr($propertyName, 0, 1) === '$')
					{
						list($propertyDescription) = preg_split('~\s+#~', $propertyDescription, 2); // Zahození komentáře
						$definition = Utils::strToArray($propertyDescription);
						if ($definition)
						{
							// array('id' => 'foo', 'x', 'y') ==> array('id', 'foo', 'x', 'y')
							$strategy = key($definition);
							$strategyValue = array_shift($definition);
							array_unshift($definition, (substr($propertyType, -2) === '[]')); // array of elements
							array_unshift($definition, $strategyValue); // strategy value
							array_unshift($definition, $strategy); // strategy
							$this->shortcuts[substr($propertyName, 1)] = $definition;
						}
					}
				}
			}
		}
		return $this->shortcuts;
	}

	/**
	 * Zkonrtoluje vlastnosti elementu.
	 * @param Element $element Kontrolovaný element.
	 * @param string $shortcutId Název zkratky v chybové zprávě.
	 * @param string|NULL $expectedTagName Požadovaný název elementu.
	 * @param array|NULL $expectedAttribs Požadované hodnoty atributů.
	 * @throws ViewStateException
	 */
	private function checkTagNameAndAttributes(Element $element, $shortcutId, $expectedTagName, $expectedAttribs)
	{
		if (isset($expectedTagName) && ($actualTagName = $element->name()) !== $expectedTagName)
		{
			throw new ViewStateException(__METHOD__ . ": Element $shortcutId má být tag '{$expectedTagName}', je '$actualTagName'.");
		}
		if (isset($expectedAttribs) && is_array($expectedAttribs))
		{
			foreach ($expectedAttribs as $attribName => $expectedValue)
			{
				if (($actualValue = $element->attribute($attribName)) !== $expectedValue)
				{
					throw new ViewStateException(__METHOD__ . ": Element $shortcutId má mít atribut '$attribName' o hodnotě '$expectedValue', má hodnotu '$actualValue'.");
				}
			}
		}
	}

	/**
	 * @param string $name
	 * @return boolean
	 */
	public function __isset($name)
	{
		$shortcuts = $this->getShortcuts();
		if (isset($shortcuts[$name]))
		{
			return TRUE;
		}
		else
		{
			return parent::__isset($name);
		}
	}

	/**
	 * Zpřístupňuje zkratky k prvkům stránky definované přes anotace
	 * `@property-read Element $name strategy=value` (pokud pro `$name`
	 * neexistuje getter).
	 *
	 * Definice zkratky může navíc obsahovat název tagu a vyžadované hodnoty
	 * atributů a ty se potom kontrolují: `@property-read Element $name
	 * strategy=value, tagName, [attrib=value, another=value]`.
	 *
	 * Případný komentář se odděluje mezerou a křížkem: `@property-read Element
	 * $name strategy=value # Tohle už je komentář.`.
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function &__get($name)
	{
		$shortcuts = $this->getShortcuts();
		if (!parent::__isset($name) && isset($shortcuts[$name]))
		{
			$this->checkState();

			$criteria = $this->session->using($shortcuts[$name][0])->value($shortcuts[$name][1]);
			$expectedTagName = isset($shortcuts[$name][3]) ? $shortcuts[$name][3] : NULL;
			$expectedAttribs = isset($shortcuts[$name][4]) ? $shortcuts[$name][4] : NULL;
			if ($shortcuts[$name][2])
			{
				$elements = $this->session->elements($criteria);
				foreach ($elements as $index => $element)
				{
					$shortcutDescription = get_class($this) . '::$' . $name . "[$index]";
					$this->checkTagNameAndAttributes($element, $shortcutDescription, $expectedTagName, $expectedAttribs);
				}
				return $elements;
			}
			else
			{
				$element = $this->session->element($criteria);
				$shortcutDescription = get_class($this) . '::$' . $name;
				$this->checkTagNameAndAttributes($element, $shortcutDescription, $expectedTagName, $expectedAttribs);
				return $element;
			}
		}
		else
		{
			return parent::__get($name);
		}
	}

	/**
	 * Po změně URL tato metoda zjistí, na který z page objektů vyjmenovaných
	 * v `@return` anotaci volající metody jsme přešli.
	 * @return IPageObject
	 */
	protected function getNextPage()
	{
		$backtrace = debug_backtrace();
		return $this->getNextPageFromList(Utils::getReturnTypes($backtrace[1]['class'], $backtrace[1]['function']), $backtrace[1]['class']);
	}

	/**
	 * Po změně URL tato metoda zjistí, na který z vyjmenovaných page objektů jsme přešli.
	 *
	 * @param array $returnTypes Možné návratové typy.
	 * @param string $definingClass Třída, ze které se bere namespace v případě relativních názvů tříd.
	 * @return IPageObject
	 */
	private function getNextPageFromList($possibleReturnTypes, $definingClass)
	{
		foreach ($possibleReturnTypes as $returnType)
		{
			// \Foo ==> \Foo
			// Foo ==> \ClassNamespace\Foo (pokud existuje)
			// Foo ==> \Foo (pokud \ClassNamespace\Foo neexistuje)
			if ($returnType{0} !== '\\')
			{
				$absolutizedReturnType = '\\' . ClassType::from($definingClass)->getNamespaceName() . '\\' . $returnType;
				$returnType = class_exists($absolutizedReturnType) ? $absolutizedReturnType : '\\' . $returnType;
			}

			if ($this instanceof $returnType)
			{
				$nextPage = $this;
			}
			else
			{
				if (!is_subclass_of($returnType, 'Se34\IPageObject'))
				{
					$backtrace = debug_backtrace();
					$className = get_class($this);
					if ($backtrace[1]['object'] === $this && $backtrace[1]['function'] === '__call')
					{
						$method = "magické metody $className::{$backtrace[2]['function']}()";
					}
					else
					{
						$method = "metody $className::{$backtrace[1]['function']}()";
					}
					throw new \UnexpectedValueException(__METHOD__ . ": Návratový typ $method je '$returnType' a ten " . (class_exists($returnType) ? 'neexistuje.' : " není typu 'Se34\IPageObject'."));
				}
				$nextPage = new $returnType($this->session);
			}
			try {
				$nextPage->checkState();
				return $nextPage;
			} catch (\Se34\ViewStateException $viewStateException) {
				;
			}
		}
		throw $viewStateException;
	}

	/**
	 * Provádí volání "magických" metod z anotací.
	 *
	 * Například `@method SomePage clickFoo()` definuje magickou metodu,
	 * jejíž volání zavolá `$this->foo->click()` a vrátí objekt typu `SomePage`.
	 * Pokud `$this` je instancí toho typu, vrátí se `$this`, jinak se vytváří
	 * nový objekt. V každém případě se na návratovém objektu volá metoda {@see
	 * self::checkState()}.
	 *
	 * Návratových typů může být v anotaci víc, oddělené svislítkem - postupně
	 * se zkouší a použije se první, u kterého projde `checkState()`.
	 *
	 * Další magickou metodou je fillShortcut - pokud je definovaná příslušná
	 * zkratka, přeloží se na $this->shortcut->value() a vrátí $this.
	 *
	 * @param string $name Název volané metody.
	 * @param array $args
	 * @return mixed
	 */
	public function __call($name, $args)
	{
		if (!isset($this->methods))
		{
			foreach ($this->getThisClasses() as $className)
			{
				$annotations = Utils::getClassAnnotations($className);
				$methodAnnotations = isset($annotations['method']) ? $annotations['method'] : array();
				foreach ($methodAnnotations as $methodAnnotations)
				{
					if (preg_match('~^(?<type>[^\s]+)\s+(?<methodName>[^\s(]+)~', $methodAnnotations, $matches))
					{
						$returnType = $matches['type'];
						$fullMethodName = $matches['methodName'];
						if (preg_match('~^(?<elementMethod>[[:lower:]]+)(?<elementShortcut>.*)~', $fullMethodName, $matches))
						{
							$elementMethod = $matches['elementMethod'];
							$elementShortcut = isset($this->{$matches['elementShortcut']}) ? $matches['elementShortcut'] : lcfirst($matches['elementShortcut']);
							if (isset($this->$elementShortcut))
							{
								$this->methods[$fullMethodName] = array(
									$elementShortcut,
									$elementMethod,
									$returnType,
									$className
								);
							}
						}
					}
				}
			}
		}
		if (isset($this->methods[$name]))
		{
			$elementShortcut = $this->methods[$name][0];
			$elementMethod = $this->methods[$name][1];
			callback($this->$elementShortcut, $elementMethod)->invokeArgs($args);
			$this->session->waitForDocument();
			return $this->getNextPageFromList(explode('|', $this->methods[$name][2]), $this->methods[$name][3]);
		}
		elseif (
				substr($name, 0, 4) === 'fill'
				&& (
				isset($this->{$shortcut = substr($name, 4)})
				|| isset($this->{$shortcut = lcfirst(substr($name, 4))})
				)
		)
		{
			callback($this->$shortcut, 'value')->invokeArgs($args);
			return $this;
		}
		else
		{
			return parent::__call($name, $args);
		}
	}

}
