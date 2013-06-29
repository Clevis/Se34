<?php

namespace Se34;

use Nette\Object;
use Nette\Reflection\ClassType;

/**
 * @property-read BrowserSession $session
 */
class PageComponent extends Object implements IPageComponent
{

	/** @var \Se34\BrowserSession */
	protected $session;
	/** @var IElementsFinder */
	protected $parent;
	/** @var array */
	protected $parameters;

	/**
	 * Processed shortcuts from property-read annotations. Don't access this
	 * directly, use `$this->getShortcuts()`.
	 *
	 * <code>
	 * array(
	 *    // @property-read Element $propertyName strategy=value, expectedTagName, (attrib = value)
	 *    'propertyName' => array(
	 *        'strategy',
	 *        'value',
	 *        FALSE, // is array of elements
	 *        'expectedTagName',
	 *        array('attrib' => 'value'),
	 *    ),
	 * );
	 * </code>
	 * @var array
	 */
	private $shortcuts;

	/**
	 * Processed method annotations.
	 *
	 * <code>
	 * array(
	 *    // @method Foo clickBar()
	 *    'clickBar' => array(
	 *        'bar',       // shortcut name
	 *        'click',     // name of the method to call on the shortcut
	 *        'Foo',       // class name of the next page object
	 *        'SomeClass', // defining class (for namespace)
	 *    )
	 * );
	 * </code>
	 * @var array
	 */
	private $methods;

	/**
	 * @param BrowserSession $session
	 * @param IElementsFinder $parent
	 * @param array $parameters
	 */
	public function __construct(BrowserSession $session, IPageComponent $parent = NULL, array $parameters = NULL)
	{
		if ($parent === NULL)
		{
			throw new \InvalidArgumentException('PageComponent needs a parent.'); // todo Se34\InvalidArgumentException
		}
		$this->session = $session;
		$this->parent = $parent;
		$this->parameters = $parameters;
	}

	/**
	 * Checks that this page is open in browser.
	 * @throws ViewStateException
	 */
	public function checkState()
	{
		return $this->parent->checkState();
	}

	/**
	 * @return BrowserSession
	 */
	public function getSession()
	{
		return $this->session;
	}

	/**
	 * Calls fill method for each item.
	 *
	 * <code>
	 * // This code:
	 * $page->fill(array(
	 *    'foo' => 'bar',
	 *    'e' => 'mc^2',
	 * ));
	 * // Does this:
	 * $page->fillFoo('bar')
	 * $page->fillE('mc^2');
	 * </code>
	 *
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
	 * Gets names of all classes this object is instance of.
	 *
	 * @return array
	 */
	private function getThisClasses()
	{
		return Utils::getWholeLineageOfClass(get_class($this));
	}

	/**
	 * Provides processed shortcuts definitions (property-read annotations).
	 *
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
					// Splitting @property-read $propertyType $propertyName $propertyDescription
					list($propertyType, $propertyName, $propertyDescription) = preg_split('~\s+~', $property, 3) + array(NULL, NULL, NULL);
					if (substr($propertyName, 0, 1) === '$')
					{
						list($propertyDescription) = preg_split('~\s+#~', $propertyDescription, 2); // Throw away the comment
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
	 * Checks element name and attributes.
	 *
	 * @param Element $element Element to examine.
	 * @param string $shortcutId Name of the shortcut (needed for the eventual error message).
	 * @param string|NULL $expectedTagName Expected tag name.
	 * @param array|NULL $expectedAttribs Expected values of attributes.
	 * @throws ViewStateException
	 */
	private function checkTagNameAndAttributes(Element $element, $shortcutId, $expectedTagName, $expectedAttribs)
	{
		$actualTagName = $element->name();
		if (isset($expectedTagName) && $actualTagName !== $expectedTagName)
		{
			throw new ViewStateException(__METHOD__ . ": Element '$shortcutId' is expected to be a tag '{$expectedTagName}', but is '$actualTagName'.");
		}
		if (isset($expectedAttribs) && is_array($expectedAttribs))
		{
			foreach ($expectedAttribs as $attribName => $expectedValue)
			{
				$actualValue = $element->attribute($attribName);
				if ($actualValue !== $expectedValue)
				{
					throw new ViewStateException(__METHOD__ . ": Expected value of '$attribName' is '$expectedValue' on element '$shortcutId', actual value is '$actualValue'.");
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
	 * Provides access to shortcuts of page elements defined using property-read
	 * annotations (eg. `property-read Element $name strategy=value`).
	 *
	 * If there is a getter for the value (eg. method `getFoo()` for `$foo` property),
	 * the getter is used instead.
	 *
	 * Definition of a shortcut may include the tag name and required attributes
	 * values. These are checked then. Eg. `property-read Element $name
	 * strategy=value, tagName, [attrib=value, another=value]`.
	 *
	 * Eventual comment may be separated by a space and a hash: `property-read
	 * Element $name strategy=value # Now this is a comment.`
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

			$criteria = array('strategy' => $shortcuts[$name][0], 'selector' => $shortcuts[$name][1]);
			$expectedTagName = isset($shortcuts[$name][3]) ? $shortcuts[$name][3] : NULL;
			$expectedAttribs = isset($shortcuts[$name][4]) ? $shortcuts[$name][4] : NULL;
			if ($shortcuts[$name][2])
			{
				$elements = $this->findElements($criteria['strategy'], $criteria['selector']);
				foreach ($elements as $index => $element)
				{
					$shortcutDescription = get_class($this) . '::$' . $name . "[$index]";
					$this->checkTagNameAndAttributes($element, $shortcutDescription, $expectedTagName, $expectedAttribs);
				}
				return $elements;
			}
			else
			{
				$element = $this->findElement($criteria['strategy'], $criteria['selector']);
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
	 * This method finds out on which page are we (if any), from the list of
	 * page object types on `return` annotation.
	 *
	 * @return IPageObject
	 */
	protected function getNextPage()
	{
		$backtrace = debug_backtrace();
		return $this->getNextPageFromList(Utils::getReturnTypes($backtrace[1]['class'], $backtrace[1]['function']), $backtrace[1]['class']);
	}

	/**
	 * This method can find out, on which of named page object types we are.
	 *
	 * @param $possibleReturnTypes
	 * @param string $definingClass Class which namespace is taken as a reference to relatively defined class names. Todo do this more clever
	 * @internal param array $returnTypes Possible return types.
	 * @return IPageObject
	 */
	private function getNextPageFromList($possibleReturnTypes, $definingClass)
	{
		foreach ($possibleReturnTypes as $returnType)
		{
			// \Foo ==> \Foo
			// Foo ==> \ClassNamespace\Foo (if it exists)
			// Foo ==> \Foo (if \ClassNamespace\Foo doesn't exist)
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
					throw new \UnexpectedValueException(__METHOD__ . ": Návratový typ $method je '$returnType' a ten " . (class_exists($returnType) ? 'neexistuje.' : " není typu 'Se34\\IPageObject'."));
				}
				$nextPage = new $returnType($this->session);
			}
			try
			{
				$nextPage->checkState();
				return $nextPage;
			}
			catch (\Se34\ViewStateException $viewStateException)
			{
				;
			}
		}
		throw $viewStateException;
	}

	/**
	 * Performs calling "magic" methods from annotations.
	 *
	 * For example, annotation `method SomePage clickFoo()` defines a magic
	 * method that calls `$this->foo->click()` and returns an object of type
	 * `SomePage`. If `$this` is instance of that type, it will return `$this`.
	 * Otherwise it instantiates a new object.
	 *
	 * Either way it calls the method {@see self::checkState()} afterwise on the
	 * return object.
	 *
	 * The annotation may contain more return types, separated by vertical bar.
	 * First that match is returned back.
	 *
	 * Another magic method is fillShortcut - if it is defined such a shortcut,
	 * it will translate to `$this->shortcut->value($input)`, but it returns
	 * `$this`.
	 *
	 * @param string $name Name of called method.
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
				foreach ($methodAnnotations as $methodAnnotation)
				{
					if (preg_match('~^(?<type>[^\s]+)\s+(?<methodName>[^\s(]+)~', $methodAnnotation, $matches))
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

	/**
	 * Find the first element that matches the criteria and return it. Or throw
	 * an exception.
	 *
	 * @param string $strategy
	 * @param string $selector
	 * @return Element
	 */
	public function findElement($strategy, $selector)
	{
		$this->parent->findElement($strategy, $selector);
	}

	/**
	 * Find all elements that match given criteria. If none found, than return
	 * an empty array.
	 *
	 * @param string $strategy
	 * @param string $selector
	 * @return Element[]
	 */
	public function findElements($strategy, $selector)
	{
		$this->parent->findElements($strategy, $selector);
	}

}
