<?php

namespace Se34;

interface IPageComponent extends IElementsFinder
{

	public function __construct(IElementsFinder $parent, array $parameters = array());

}
