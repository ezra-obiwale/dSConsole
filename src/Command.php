<?php

namespace dScribe\Console;

/**
 * Description of Command
 *
 * @author Ezra
 */
class Command {

	/**
	 * The script to which this command belongs to
	 * @var string
	 */
	private $script;

	/**
	 * Description to be used in help summary
	 * @var string
	 */
	private $shortDescription;

	/**
	 * Descriptions to be used in main help
	 * @var array
	 */
	private $longDescription;

	/**
	 * Array of arguments for the command
	 * @var array
	 */
	private $arguments;

	/**
	 * Callback to be applied when command is called
	 * @var callable
	 */
	private $callback;

	/**
	 * How the command is to be used
	 * @var array
	 */
	private $usage = 'command';

	/**
	 * Array of options the command supports
	 * @var array
	 */
	private $options = [];

	/**
	 * Array of examples of using the command
	 * @var array
	 */
	private $examples = [];

	/**
	 * Creates an instance of Command
	 * @return Command
	 */
	public static function instance() {
		return new self;
	}

	/**
	 * Creates a short description for the command to be used in help summary
	 * @param string $description
	 * @return Command
	 */
	public function shortDescription($description) {
		$this->shortDescription = $description;
		if (!count($this->longDescription)) $this->longDescription($description);
		return $this;
	}

	/**
	 * Creates long descriptions for the command to be used in help
	 * @param string|array $description
	 * @return Command
	 */
	public function longDescription($description) {
		$this->longDescription = is_array($description) ? $description : [$description];
		return $this;
	}

	/**
	 * Indicates an accepted parameter
	 * @param string $name
	 * @param string $description
	 * @param boolean $required
	 * @return Command
	 */
	public function withParam($name, $description, $required = false) {
		$this->arguments[$name] = [
			'required' => $required,
			'description' => $description
		];
		return $this;
	}

	/**
	 * Sets the callback function to call when the command is executed
	 * @param string|callable $callback Name of method in script or a callable to run
	 * @return Command
	 */
	public function apply($callback) {
		$this->callback = $callback;
		return $this;
	}

	/**
	 * Sets the defaults for the command
	 * @param string $script
	 * @param string $callback
	 * @return Command
	 */
	public function setDefaults($script, $callback) {
		if (!$this->script) $this->script = $script;
		if (!$this->callback) {
			$this->callback = $callback;
			$this->callbackOnScript = true;
		}
		return $this;
	}

	/**
	 * Checks if the command is owned by the given script
	 * @param object|string $script
	 * @return boolean
	 */
	public function by($script) {
		return is_object($script) ? $this->script == get_class($script) : $this->script == $script;
	}

	/**
	 * Indicates an available option
	 * @param string|array $name The name(s) of the options
	 * This may be an array of the short and long options
	 * @param string $description
	 * @return Command
	 */
	public function hasOption($name, $description) {
		if (is_array($name)) $name = join(', ', $name);
		$this->options[$name] = $description;
		return $this;
	}

	/**
	 * 
	 * @param string $title
	 * @param string|array $example String containing example or array of strings where each item is
	 * a different line
	 * @return \dScribe\Console\Command
	 */
	public function addExample($title, $example) {
		$this->examples[$title] = !is_array($example) ? [$example] : $example;
		return $this;
	}

	/**
	 * Runs the command
	 * @param array $args
	 * @return mixed
	 */
	final public function run(array $args) {
		if ($this->callback) {
			return call_user_func_array(is_string($this->callback) ?
							[$this->script, $this->callback] : $this->callback, $args);
		}
	}

	/**
	 * Provides read-only access to properties
	 * @param string $name
	 * @return mixed
	 */
	final public function __get($name) {
		if ($name === 'usage' && $this->usage == 'command') {
			if (count($this->options)) $this->usage .= ' [options]';
			if (count($this->arguments)) $this->usage .= ' [arguments]';
		}
		return $this->$name;
	}

}
