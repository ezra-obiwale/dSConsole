<?php

namespace dScribe\Console;

use ErrorException,
    Exception,
    Util;

/**
 * Description of AConsole
 *
 * @author Ezra
 */
class Console {

    /**
     * Indicates whether console is already running. Guards against multiple console runs.
     * @var boolean
     */
    private static $running = false;

    /**
     * Array of application configurations
     * @var array
     */
    private static $config;

    /**
     * The last thrown error
     * @var Exception
     */
    private static $lastError;

    /**
     * Sets up the console
     */
    private static function setup() {
        if (php_sapi_name() == 'cgi') {
            die('Unsupported SAPI - please use the CLI binary');
        }
        /**
         * Sets up CLI environment based on SAPI and PHP version
         */
        if (version_compare(phpversion(), '4.3.0', '<')) {
// Handle output buffering
            @ob_end_flush();
            ob_implicit_flush(TRUE);

// PHP ini settings
            set_time_limit(0);
            ini_set('track_errors', TRUE);
            ini_set('html_errors', FALSE);
            ini_set('magic_quotes_runtime', FALSE);

// Define stream constants
            define('STDIN', fopen('php://stdin', 'r'));
            define('STDOUT', fopen('php://stdout', 'w'));
            define('STDERR', fopen('php://stderr', 'w'));

// Close the streams on script termination
            register_shutdown_function(
                    create_function('',
                                    'fclose(STDIN); fclose(STDOUT); fclose(STDERR); return true;')
            );
        }

        set_exception_handler(function($ex) {
            Console::error($ex);
        });
    }

    /**
     * Runs the console
     * @param array $args
     * @param array $config The configuration on with console scripts should run
     * @return void
     */
    final public static function run($args, array $config = []) {
        if (self::$running) return;
        self::$running = true;

        self::setup();
        self::$config = $config;

        $option = (self::isOption($args[0])) ? array_shift($args) : null;
        if (!count($args)) {
            $args[] = self::defCom();
        }
        if ($option) $args[] = $option;
        $command = array_shift($args);
        self::runCommand($command, $args);
        self::end();
    }

    /**
     * Checks if the given string is an option
     * @param string $string
     * @return boolean
     */
    final public static function isOption($string) {
        return substr($string, 0, 1) == '-';
    }

    /**
     * Fetches the default command which is run when no arguments are provided
     * @return string
     */
    private static function defCom() {
        return '__:main';
    }

    /**
     * Runs the giveen command
     * @param string $command
     * @param array $args
     * @return mixed
     */
    private static function runCommand($command, array $args) {
        $split = explode(':', $command);
        if (count($split) == 1) {
            $split[1] = 'main';
        }

        $split[0] = Util::_toCamel($split[0]);
        $split[1] = Util::_toCamel($split[1]);

        $options = self::parseOptions($args);
        if (Script::callScriptMethod($split[0], '_processMainOptions', [$options, $command]))
                return;
        $Command = self::getCommand($split[0], $split[1], $options);
        if (!is_object($Command) || (is_object($Command) &&
                !is_a($Command, get_class(Command::instance()))))
                $Command = self::getCommand('__', 'main');
        return $Command->run($args);
    }

    /**
     * Parses and removes options from the givem args
     * @param array $args
     * @return array Array of options
     */
    private static function parseOptions(array &$args) {
        $options = [];
        foreach ($args as $ky => $arg) {
            // skip real arguments
            if (!self::isOption($arg)) continue;
            unset($args[$ky]);
            $arg = substr($arg, 1);
            if (self::isOption($arg)) {
                $arg = substr($arg, 1);
                if (stristr($arg, '=')) {
                    $exp = explode('=', $arg);
                    $options[$exp[0]] = $exp[1];
                }
                else $options[] = $arg;
                continue;
            }
            if (strlen($arg) > 1) {
                for ($i = 0; $i < strlen($arg); $i++) {
                    $options[] = $arg[$i];
                }
                continue;
            }
            $options[] = $arg;
        }
        return $options;
    }

    /**
     * Gets the full qualified name of the given script
     * @param string $scriptAlias
     * @return string
     */
    private static function getScriptClass($scriptAlias) {
        if ($scriptAlias === '__') return 'dScribe\Console\Script';
        else if (is_array(self::$config['scripts'] &&
                        array_key_exists($scriptAlias, self::$config['scripts'])))
                return self::$config['scripts'][$scriptAlias];
        else throw new ErrorException('Invalid script <' . $scriptAlias . '>');
    }

    /**
     * Fetches the Command object for the given script command
     * @param string $script
     * @param string $command
     * @param array $options
     * @return Command
     * @throws ErrorException
     */
    public static function getCommand($script, $command, array $options = array()) {
        if (!Script::isAllowedCommand($command))
                throw new ErrorException("Invalid command <$script:$command>");
        $Script = self::getScriptClass($script);
        if ($Command = call_user_func_array([$Script, $command], [])) {
            call_user_func_array([$Script, '_init'], [$Command, $options]);
            if (is_object($Command)) $Command->setDefaults($Script, '_' . $command);
            return $Command;
        }
    }

    /**
     * Gets (settings from) the config
     * @param $_ Pass in as many params to indicate the array path to required config.
     * If last parameter is boolean, that will indicate whether to throw exception if required config
     * is not found. Defaults to TRUE.
     * @return mixed
     * @throws Exception
     */
    public static function config() {
        $args = func_get_args();
        if (count($args) === 0) {
            return static::$config;
        }
        $except = true;
        if (gettype($args[count($args) - 1]) === 'boolean') {
            $except = $args[count($args) - 1];
            unset($args[count($args) - 1]);
        }

        $value = null;
        $path = '';
        $error = false;

        foreach ($args as $key => $arg) {
            if ($key === 0) {
                $path = '$config[' . $arg . ']';

                if (!isset(static::$config[$arg])) {
                    $error = true;
                    break;
                }

                $value = & static::$config[$arg];
            }
            else {
                $path .= '[' . $arg . ']';

                if (!isset($value[$arg])) {
                    $error = true;
                    break;
                }

                $value = & $value[$arg];
            }
        }

        if ($error && $except) {
            throw new Exception('Invalid config path "' . $path . '"', true);
        }
        elseif ($error) {
            return null;
        }

        return $value;
    }

    /**
     * Writes out to the console
     * @param string $output
     * @return integer
     */
    final public static function write($output = "") {
        return fwrite(STDOUT, $output . "\n");
    }

    /**
     * Reads in from the console
     * @return string
     */
    final public static function read() {
        return fgets(STDIN);
    }

    /**
     * Terminates the console program
     * @param integer $code
     */
    final public static function end($code = 0) {
        exit($code);
    }

    /**
     * Output the error and terminates the application
     * @param Exception $ex
     */
    public static function error(Exception $ex) {
        self::$lastError = $ex;
        fwrite(STDERR, $ex->getMessage() . ' in ' . $ex->getFile() . ' on #' . $ex->getLine());
        self::end(1);
    }

    /**
     * Fetches the last thrown error
     * @return Exception
     */
    public static function lastError() {
        return self::$lastError;
    }

    /**
     * Registers a console script or scripts directory
     * @param string $path Path to the script or directory
     * @param boolean $isDir Indicates whether path is to directory
     * @return boolean
     */
    public static function register($path, $isDir = false) {
        $registry = [$isDir ? 'dirs' : 'scripts' => [$path]];
        if (Util::updateConfig(CONFIG . 'global.php',
                               [
                    'console' => $registry
                ])) {
            self::resetList();
            return true;
        }
        return false;
    }

    private static function resetList() {
        $cache = DATA . md5('CMD_MANIFEST');
        if (is_file($cache)) unlink($cache);
    }

}
