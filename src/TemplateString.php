<?php

namespace MintyPHP;

class TemplateString
{
	private $string;

	public function __construct(string $string)
	{
		$this->string = $string;
	}

	public function __toString(): string
	{
		return $this->string;
	}
}
