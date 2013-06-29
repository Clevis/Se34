Se34 - pomocné třídy pro práci se Seleniem
==========================================

# Toto README je silně neaktuální, připravuju lepší.
# This README is outdated, better documentation coming soon.

Tato knihovna závisí na:
- Nette
- PHPUnit
- PHPUnit_Selenium

V každém projektu je potřeba vytvořit tyto project-specific třídy:

- **SeleniumConfigurator** - Vytváří systémový kontejner a testovací databázi.
- **SeleniumRouteList** - Ke každé URL přidává parametr s názvem používané databáze.
- **SeleniumTemplateFactory** - Přenastavuje `$baseUrl`, aby linky na statické soubory vedly do normálního rootu.
- **SeleniumTestCase extends Se34\TestCase** - Musí implementovat `createContext()`.

Systémový kontejner, vytvářený SeleniumConfiguratorem, musí poskytovat tyto parametry:

```
parameters:
	selenium:
		server: http://localhost:4444
		desiredCapabilities
			browserName: firefox
		baseUrl: http://localhost/projekt/test-www/
		# keepWindowOpenOnFailure: FALSE # Volitelné, výchozí hodnota je TRUE.
```

Dál je potřeba vytvořit alternativní root (přes který budou k aplikaci přistupovat testy):
- index.php - V podstatě normální bootstrap, ale používá SeleniumConfigurator.
- tmp
- `.htaccess` - Copy&paste z rootu, ale přidat `Deny from all` a `Allow from 127.0.0.1`.

Selenium Server
---------------

Pro spuštění testů je potřeba:
- Java.
- [Selenium Server](http://seleniumhq.org/download/)
- Pro spouštění jiných prohlížečů než Firefox (lze nastavit v `tests/inc/selenium.neon`):
	- [Internet Explorer driver](http://seleniumhq.org/download/) (stáhnout a umístit do PATH)
	- [Chrome driver](http://code.google.com/p/chromedriver/downloads/list) (stáhnout a umístit do PATH)
	- **Opera Driver** je součástí aktuálního Selenium Serveru, ale zatím [nepodporuje Alert API](https://github.com/operasoftware/operadriver/issues/31), takže některé testy neprojdou.
	- **HtmlUnit** se mi zatím nepodařilo rozběhat, ale taky by měl být součástí Serveru.

Před spuštěním samotných testů je nutné spustit Selenium Server, ideálně z příkazové řádky (protože pak lze snadno killnout ctrl-c):

```
java -jar selenium-server-standalone-2.25.0.jar
```

Tahle knihovna umožňuje ponechat otevřené okno prohlížeče i po skončení testu (hodí se to při chybě). V takovém případě ale zůstane v paměti viset proces Chrome Driveru (asi to dělají i jiné drivery). Po nějaké době se jich může nakupit hodně a můžou zdržovat, na Windows se killnou `taskkill /F /IM chrome-driver.exe`.

Přístup k Seleniovým příkazům
-----------------------------

Základní třída zpřístupňuje objekt session, přes který se volají selenové příkazy:

```php
public function testFoo()
{
	$this->session->byXpath('//button[text()="Text"]')->click();
}
```

Předtím jsem zkoušel `PHPUnit_Extensions_Selenium2TestCase`, ve které se ke všemu přistupuje přes `$this`, ale přišlo mi to jako děsnej bordel.

Selenium 2 (WebDriver)
----------------------

Testy používají Selenium 2. Hlavní rozdíly oproti Seleniu 1:

- Jiné API, více objektově orientované. Místo `$session->type('name', 'value')` se píše `$session->byName('name')->value('value')` - tzn. nejdřív vyberete element a potom na něm provedete nějakou akci. Pokud element není nalezen, vyskočí RuntimeException.
- Provádění příkazů se víc blíží tomu, jak by to dělal uživatel. Například:
  - Příkaz *value* na objektu elementu nepřepisuje přímo jeho javascriptovou vlastnost *value*, ale předá elementu focus a simuluje stisky kláves. Díky tomu například:
    - Lze odeslat formulář enterem.
    - Lze vybrat ze selectu napsáním prvních pár znaků.
    - Provedou se případné JS události navěšené na stisk kláves.
  - Není možné kliknout na element, pokud není vidět. Pokud se nějaké menu rozbalí až po najetí na nějaký jiný prvek, je potřeba nejdřív na tento prvek "najet" (př. `$session->moveto($session->byLinkText('Menu'))`).
  - Stejně tak nelze na nic kliknout, pokud je přes celou stránku Laděnka (stejně jako ve skutečnosti). Ovšem Laděnku standardně odchytává BrowserSession.

WebDriver API
-------------

Dokumentace WebDriver API příkazů je zde: http://code.google.com/p/selenium/wiki/JsonWireProtocol

Příkazy z WebDriver API, které BrowserSession zatím nenabízí, lze případně velmi snadno přidat (`BrowserSession::initCommands()`).

WaitForAjax atd.
----------------

Session objekt nabízí několik čekacích metod:

- `waitForAlert()` - pokud je na stránce klasický JS dialog (alert, confirm, nebo prompt), tak většina příkazů končí chybou. Jenže dokud se ten alert neobjeví, tak končí chybou i příkaz `alertText`. Tahle metoda proto provádí `alertText` tak dlouho, dokud neskončí úspěšně. Potom je možné dál s dialogem pracovat:
  - `alertText($text)` - vloží text do promptu.
  - `acceptAlert()` - potvrdí dialog.
  - `dismissAlert()` - zruší dialog.
- `waitForCondition($condition)` - čeká na splnění nějaké JS podmínky.
- `waitForDocument()` - čeká na `document.readyState == "complete"`
- `waitForAjax()` - čeká na `jQuery.active == 0`

Asserts
-------

`Se34\TestCase` přidává tyto aserce:

- `assertPresenter`
- `assertTagName`
- `assertTagAttributes`
- `assertElementEquals`

Page Objects
------------

Aby testy nebyly tolik závislé na aktuálním HTML kódu, je vhodné použít tzv. Page Objects. Články o návrhovém vzoru Page Objects:
- http://blog.activelylazy.co.uk/2011/07/09/page-objects-in-selenium-2-0/
- http://code.google.com/p/selenium/wiki/PageObjects
- http://css.dzone.com/articles/page-object-pattern

Základní třída pro page objects
-------------------------------

`Se34\PageObject`

- Podporované anotace:
  - `@property-read` - zkratky pro přístup k elementům.
  - `@method` - metody pro akce nad elementy.
- Provádí kontrolu stavu.
  - Při přístupu k elementům přes zkratky.
  - Při volání magických metod.
  - Na požádání (`->checkState()`).

Příklad: todo

Formát `@property-read`
-----------------------

```
/**
 * @property-read Element $searchBox name=q, input, (type=text) # Poznámka
 * @property-read Element[] $allLinks xpath='//a', a # Příklad na pole elementů
 */
```

- `@property-read`
- `Element` - pokud je `Element[]` (resp. `Cokoli[]`), tak jde o pole všech elementů, které vyhovují kritériím, jinak jeden element.
- `$searchBox` - název zkratky, nezapomenout `$`.
- `name = q` - [locator strategy = value](http://code.google.com/p/selenium/wiki/JsonWireProtocol#/session/:sessionId/element)
- `input` - nepovinné. Pokud je uvedeno, kontroluje se název nalezeného elementu/nalezených elementů.
- `(type = text)` - nepovinné. Pokud je uvedeno, kontrolují se hodnoty atributů.
- ` # Poznámka` - poznámku lze oddělit mezerou a křížkem.

Část mezi názvem property a poznámkou se parsuje Neonem.

Formát `@method`
----------------

```
/**
 * @method ReturnType methodShortcut() Description
 */
```

- `@method`
- `ReturnType` - návratový typ, musí implementovat `Se34\IPageObject`. Pokud nezačíná zpětným lomítkem, hledá se nejdřív v namespacu definující třídy (tj. třídy, ve které je uvedená ta anotace `@method`).

  Pokud `$this instanceof ReturnType`, tak se vrátí přímo `$this`.
- `methodShortcut` - volání `$page->methodShortcut($arg)` se převede na `$page->shortcut->method($arg)`, rozdíl je jen v té návratovce.

getNextPage()
-------------

Tam, kde nestačí `@method` anotace, lze pro výběr následujícího page objectu použít tuto protected metodu:

```php
/**
 * @return AnotherPage|YetAnotherPage Možné návratové typy.
 */
public function clickSomething()
{
	$this->something->click();
	$this->session->waitForAjax();
	return $this->getNextPage(); // vybírá z typů v @return
}
```

fill()
------

Podpůrná magie pro vyplňování formulářů:

```php
$page->fillFoo($value); // $page->foo->value($value);

$page->fill(array(
	'foo' => 'bar',     // $page->fillFoo('bar');
	'e' => 'mc^2',      // $page->fillE('mc^2');
));
```
