<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace dScribe\Console;

use dScribe\Console\Console as CMD,
	dScribe\Console\Command,
	Util;

/**
 * Description of App
 *
 * @author Ezra Obiwale
 * 
 * @method null write(string $output) Writes the given string to the console
 * @method mixed read() Reads input from the console
 * @method mixed callScriptMethod(string $script, string $methodName) Calls the given method on 
 * the given script
 * @method Command newCommand() Creates a new Command instance
 * @method Command|array getCommand(string $script, string $command) Gets a script's 
 * command
 * @method boolean isAllowedCommand(string $command) Checks if the given command is allowed or not
 * @method array commands() Fetches all the commands - allowed and unallowed
 * @method mixed error(string $message) Writes the error message to the console and terminates the 
 * main script
 */
class Script {

	/**
	 * Array of options applied to the current command
	 * @var array
	 */
	protected static $options = [];

	/**
	 * The command currently executed
	 * @var Command
	 */
	private static $currentCommand;

	/**
	 * Fetches the version of the script
	 * @return string
	 */
	public static function _version() {
		return 'dScribe Framework version 2.3.2';
	}

	/**
	 * Called before command to see if main options apply
	 * @param array $options
	 * @param string $command
	 * @return boolean TRUE indicates that the process has been handled and Console should terminate
	 */
	final public static function _processMainOptions(array $options, $command) {
		if (in_array('v', $options) || in_array('version', $options)) {
			self::write(static::_version());
			return true;
		}
		if ((in_array('h', $options) || (in_array('help', $options)) && strstr($command, ':'))) {
			self::_help($command);
			return true;
		}
	}

	/**
	 * The entry command to every script
	 * @return Command
	 */
	public static function main() {
		$Command = self::newCommand()->apply('_help');
		if (self::_isSelf())
				$Command->hasOption('-c, --config[=VARNAME]',
						'Displays config settings [for the given variable]')
					->hasOption('-h, --help', 'Display this help message')
					->hasOption('-n, --no-interaction', 'Do not ask any interactive question')
					->hasOption('-q, --quiet', 'Do not output any message')
					->hasOption('-V|VV|VVV, --verbose',
				 'Increase the verbosity of messages: 1 for normal,'
							. ' 2 for more verbose and 3 for debug')
					->hasOption('-v, --version', 'Display this application/script version')
					->hasOption('--ansi', 'Force ANSI output')
					->hasOption('--no-ansi', 'Disable ANSI output')
					->hasOption('--server[=ENV]', 'The environment under which the command should run')
					->withParam('script:command', 'The name of the script with/out the command');
		return $Command;
	}

	/**
	 * Initializes the properties
	 * @param Command $command
	 * @param array $options
	 */
	final public static function _init(Command $command, array $options) {
		self::$options = $options;
		self::$currentCommand = $command;
	}

	/**
	 * Shows the help
	 * @param string $command The command to show help for. If not provided, help is shown for the
	 * script
	 * @return mixed
	 */
	final public static function _help($command = null) {
		if ($command) {
			$split = explode(':', $command);
			$split[0] = Util::_toCamel($split[0]);
			$split[1] = Util::_toCamel($split[1]);
			self::$currentCommand = self::getCommand($split[0], $split[1]);
		}
		self::write(static::_version());
		self::write();
		// show long description only for command
		if ($command && count(self::$currentCommand->longDescription)) {
			foreach (self::$currentCommand->longDescription as $description) {
				self::write($description);
			}
			self::write();
		}
		// show usage only for command and main script
		if (($command || self::_isSelf()) && self::$currentCommand->usage) {
			self::write('Usage:');
			self::write(self::_prepareHelp(self::$currentCommand->usage, null, 1));
			self::write();
		}
		// show options only for command and main script
		if (($command || self::_isSelf()) && count(self::$currentCommand->options)) {
			self::write('Options:');
			foreach (self::$currentCommand->options as $option => $description) {
				self::write(self::_prepareHelp($option, $description, 1));
			}
			self::write();
		}

		if ($command || self::_isSelf()) {
			// show arguments for command and main script
			if (count(self::$currentCommand->arguments)) {
				self::write('Arguments:');
				foreach (self::$currentCommand->arguments as $name => $details) {
					self::write(self::_prepareHelp($name . (!$details['required'] ? ' (optional)' : ''),
									$details['description'], 1));
				}
				self::write();
			}

			// show examples for command and main script
			if (count(self::$currentCommand->examples)) {
				self::write('Examples:');
				foreach (self::$currentCommand->examples as $title => $lines) {
					foreach ($lines as $ky => $line) {
						self::write(self::_prepareHelp(!$ky ? $title : '', $line, 1));
					}
					self::write();
				}
				self::write();
			}

			if ($command) return;
		}

		self::write('Available commands:');
		return self::_renderHelp();
	}

	/**
	 * Checks to see if current script is the main Script and not a child
	 * @return booelan
	 */
	private static function _isSelf() {
		return get_called_class() === get_class();
	}

	/**
	 * Prepares the help line by parsing the spaces around it.
	 * @param string $name
	 * @param string $description
	 * @param int $indent
	 * @return string
	 */
	private static function _prepareHelp($name, $description, $indent = 0) {
		if (substr($name, 0, 2) === '--') $name = str_repeat(' ', 4) . $name;
		return self::_spaceOut(str_repeat(' ', $indent * 2) . $name) . "\t" . $description;
	}

	/**
	 * Spaces out a line
	 * @param string $name
	 * @return string
	 */
	private static function _spaceOut($name) {
		$length = 25;
		return (strlen($name) < $length) ? $name . str_repeat(' ', $length - strlen($name)) : $name;
	}

	/**
	 * Renders the help for the given script or the current one if $script is null
	 * @param string $script
	 */
	private static function _renderHelp($script = null) {
		if ($script) {
			self::_loadScriptCommandList(__DIR__ . DIRECTORY_SEPARATOR
					. ucfirst(Util::hyphenToCamel($script)) . '.php');
		}
		else {
			$SCRIPTS_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR;
			if (!self::_isSelf())
					self::_loadScriptCommandList($SCRIPTS_DIR . basename(get_called_class()) . '.php');
			else {
				$console_dirs = CMD::config('console', 'dirs', false) ? : [];
				$console_dirs[] = $SCRIPTS_DIR;
				sort($console_dirs);
				foreach ($console_dirs as $dir) {
					array_walk(scandir($dir, SORT_ASC),
						function($current, $i, $dir) {
						$file = $dir . DIRECTORY_SEPARATOR . $current;
						if (in_array($current, ['.', '..']) || is_dir($file)) return;
						self::_loadScriptCommandList($file);
					}, $dir);
				}
				foreach (CMD::config('console', 'scripts', false) ? : [] as $file) {
					self::_loadScriptCommandList($file);
				}
			}
		}
	}

	/**
	 * Loads the list of commands in the given script for help
	 * @param string $script Full path to the script to load
	 * @return mixed
	 */
	private static function _loadScriptCommandList($script) {
		$name = stristr(basename($script), '.', true);
		$commands = self::callScriptMethod($name, 'commands');
		if (!count($commands)) return; // has just the main command
		if (self::_isSelf()) self::write(self::_prepareHelp(Util::camelTo_($name), null, 1));
		foreach ($commands as $command) {
			$Command = self::getCommand($name, $command);
			self::write(self::_prepareHelp(Util::camelTo_($name) . ':'
							. Util::camelTo_($command), $Command->shortDescription, 2));
		}
	}

	/**
	 * Overloading
	 * @param string $name
	 * @param array $arguments
	 * @return mixed
	 */
	final public static function __callStatic($name, $arguments) {
		switch ($name) {
			case 'write':
				return CMD::write($arguments[0] ? : '');
			case 'read':
				return CMD::read();
			case 'callScriptMethod':
				$Script = CMD::getScript($arguments[0]);
				return call_user_func_array([$Script, $arguments[1]], $arguments[2] ? : []);
			case 'newCommand':
				return Command::instance();
			case 'getCommand':
				return CMD::getCommand($arguments[0], $arguments[1]);
			case 'isAllowedCommand':
				return substr($arguments[0], 0, 1) !== '_';
			case 'commands':
				$commands = [];
				foreach (get_class_methods(get_called_class()) as $command) {
					if ($command == 'main' || !self::isAllowedCommand($command)) continue;
					$commands[] = $command;
				}
				return $commands;
			case 'error':
				throw new \Exception($arguments[0]);
		}
	}

}
