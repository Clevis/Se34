<?php

namespace Se34;

/**
 * Neočekávaný stav na stránce.
 *
 * Buďto je zobrazená jiná stránka ({@see PageObject::checkState()}), nebo
 * nějaký element nenaplňuje definovaná očekávání ({@see PageObject::__get()}).
 *
 * @author Václav Šír
 */
class ViewStateException extends \RuntimeException
{

}
