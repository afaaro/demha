<?php
namespace System\Engine;

class Controller {
	/**
	 * @var \System\Engine\Registry
	 */
	protected \System\Engine\Registry $registry;

	/**
	 * Constructor
	 *
	 * @param \System\Engine\Registry $registry
	 */
	public function __construct(\System\Engine\Registry $registry) {
		$this->registry = $registry;
	}

	/**
	 * __get
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function __get(string $key): mixed {
		if (!$this->registry->has($key)) {
			throw new \Exception('Error: Could not call registry key ' . $key . '!');
		}

		return $this->registry->get($key);
	}

	/**
	 * __set
	 *
	 * @param string $key
	 * @param mixed $value
	 *
	 * @return void
	 */
	public function __set(string $key, mixed $value): void {
		$this->registry->set($key, $value);
	}
}