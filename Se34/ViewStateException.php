<?php

namespace Se34;

/**
 * Unexpected state of a page.
 *
 * It is either some other page displayed than it was expected ({@see PageObject::checkState()}),
 * or some element doesn't fulfill expectations ({@see PageObject::__get()}).
 *
 * @author Václav Šír
 */
class ViewStateException extends \RuntimeException
{

}
