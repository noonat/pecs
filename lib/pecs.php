<?php

namespace pecs;

if (getenv('PECS_GLOBALS') !== '0') {
	require_once __DIR__.'/pecs/globals.php';
}

/// Calls to expect() return a copy of this object. It is for all of the
/// assertions used within a spec.
class Expectation {
	function __construct($actual) {
		$this->actual = $actual;
	}
	
	function __call($method, $args) {
		$originalMethod = $method;
		if (strncmp($method, 'and_', 4) === 0) {
			$method = substr($method, 4);
		}
		if (strncmp($method, 'not_', 4) === 0) {
			$expectedResult = false;
			$method = substr($method, 4);
		}
		else {
			$expectedResult = true;
		}
		if (strncmp($method, 'to_', 3) === 0) {
			$method = substr($method, 3);
		}
		$method = '_'.ltrim($method, '_');
		$this->assert($method, $args, $expectedResult);
	}
	
	function assert($matcher, $args, $expectedResult) {
		if (!method_exists($this, $matcher)) {
			throw new \Exception("Unknown matcher \"{$matcher}\"");
		}
		$leftovers = call_user_func_array(array($this, $matcher), $args);
		if (!is_array($leftovers)) {
			$leftovers = array($leftovers);
		}
		$result = array_shift($leftovers);
		if ($result != $expectedResult) {
			if (!empty($leftovers)) {
				$format = array_shift($leftovers);
				$formatArgs = $leftovers;
			}
			else {
				$verb = trim(str_replace('_', ' ', $matcher));
				$format = "expected %s to {$verb} %s";
				$formatArgs = $args;
				array_unshift($formatArgs, $this->actual);
			}
			$formatArgs = array_map(function($arg) {
				return var_export($arg, true);
			}, $formatArgs);
			array_unshift($formatArgs, $format);
			$message = call_user_func_array('sprintf', $formatArgs);
			Runner::instance()->currentSpec->fail(new \Exception($message));
		}
	}
	
	function _equal($expected) {
		return $this->actual === $expected;
	}
	
	function _be_greater_than($expected) {
		return $this->actual > $expected;
	}
	
	function _be_less_than($expected) {
		return $this->actual < $expected;
	}
	
	function _be_at_least($expected) {
		return $this->actual >= $expected;
	}
	
	function _be_at_most($expected) {
		return $this->actual <= $expected;
	}
	
	function _be_within($min, $max) {
		return $this->actual >= $min && $this->actual <= $max;
	}
	
	function _be_a($expected) {
		$class = get_class($this->actual);
		return array(
			$class === $expected,
			'expected %s to be class %s', $class, $expected);
	}
	
	function _be_an_instance_of($expected) {
		$class = get_class($this->actual);
		return array(
			$this->actual instanceof $expected,
			'expected %s to be an instance of %s, was %s',
			$this->actual, $expected, $class);
	}
	
	function _be_null() {
		return is_null($this->actual);
	}
	
	function _be_true() {
		return $this->actual === true;
	}
	
	function _be_false() {
		return $this->actual === false;
	}
	
	function _be_empty() {
		return empty($this->actual);
	}
	
	function _be_type($expected) {
		return array(
			$type === $expected,
			'expected %s to be type %s, was %s',
			$this->actual, $expected, $type);
	}
	
	function _have_count($expected) {
		return count($this->actual) === $expected;
	}
	
	function _have_count_within($min, $max) {
		$count = count($this->actual);
		return array(
			$count >= $min && $count <= $max, $count,
			'expected %s have count within %d and %d, was %d',
			$this->actual, $min, $max, $count);
	}
}

class Suite {
	function __construct($description=null, $body=null, $parentSuite=null) {
		$this->description = $description;
		$this->body = $body;
		$this->parentSuite = $parentSuite;
		$this->suites = array();
		$this->specs = array();
	}
	
	function addSpec($description, $body) {
		$spec = new Spec($description, $body, $this);
		Runner::instance()->allSpecs[] = $spec;
		$this->specs[] = $spec;
		return $spec;
	}
	
	function addSuite($description, $body) {
		$suite = new Suite($description, $body, $this);
		Runner::instance()->allSuites[] = $suite;
		$this->suites[] = $suite;
		$suite->compile();
		return $suite;
	}
	
	function compile() {
		Runner::instance()->currentSuite = $this;
		$body = $this->body;
		if ($body) {
			$body();
		}
		Runner::instance()->currentSuite = $this->parentSuite;
	}
	
	function fullDescription() {
		if ($this->parentSuite) {
			$description = $this->parentSuite->fullDescription();
			if (!empty($description)) {
				$description .= ' ';
			}
			return $description.$this->description;
		}
		else {
			return $this->description;
		}
	}
	
	function passed() {
		return true;
	}
	
	function run() {
		Runner::instance()->formatter->beforeSuite($this);
		foreach ($this->specs as $spec) {
			$spec->run();
		}
		foreach ($this->suites as $suite) {
			$suite->run();
		}
		Runner::instance()->formatter->afterSuite($this);
	}
}

class Spec extends Suite {
	public $exceptions = array();
	
	function fail($exception) {
		$this->exceptions[] = $exception;
	}
	
	function failed() {
		return !$this->passed();
	}
	
	function passed() {
		return empty($this->exceptions);
	}
	
	function run() {
		Runner::instance()->currentSpec = $this;
		Runner::instance()->formatter->beforeSpec($this);
		$body = $this->body;
		try {
			$body();
		}
		catch (\Exception $e) {
			$this->fail($e);
		}
		Runner::instance()->formatter->afterSpec($this);
	}
}

class Runner extends Suite {
	static $instance = null;
	static function instance() {
		if (is_null(self::$instance)) {
			self::$instance = new Runner();
		}
		return self::$instance;
	}
	
	function __construct() {
		parent::__construct();
		$this->allSpecs = array();
		$this->allSuites = array();
		$this->currentSuite = $this;
		$this->formatter = new Formatter();
	}
	
	function describe($description, $body) {
		return $this->currentSuite->addSuite($description, $body);
	}
	
	function it($description, $body) {
		return $this->currentSuite->addSpec($description, $body);
	}
	
	function expect($value) {
		return new Expectation($value);
	}
	
	function run($formatter=null) {
		$this->formatter = $formatter ? $formatter : new Formatter();
		$this->formatter->before();
		parent::run();
		$this->formatter->after();
	}
}

class Formatter {
	static $colors = array(
		'bold'    => 1,
		'black'   => 30,
		'red'     => 31,
		'green'   => 32,
		'yellow'  => 33,
		'blue'    => 34,
		'magenta' => 35,
		'cyan'    => 36,
		'white'   => 37
	);
	function color($string, $color) {
		return sprintf("\033[%dm%s\033[0m", self::$colors[$color], $string);
	}

	function before() {
		$this->startTime = microtime(true);
	}
	
	function beforeSuite($suite) {
		if ($suite instanceof Runner) return;
		if (!empty($suite->specs)) {
			echo $this->color("\n{$suite->fullDescription()}\n", 'bold');
		}
	}
	
	function beforeSpec($spec) {
		echo "- {$spec->description}: ";
	}
	
	function afterSpec($spec) {
		if ($spec->passed()) {
			echo $this->color("pass\n", 'green');
		}
		else {
			echo $this->color("fail	\n", 'red');
		}
	}
	
	function afterSuite($suite) {
	}
	
	function after() {
		$passed = $failed = 0;
		$specs = Runner::instance()->allSpecs;
		foreach ($specs as $spec) {
			if ($spec->failed()) {
				$failed += 1;
				foreach ($spec->exceptions as $e) {
					echo "\nFAILURE:\n";
					echo $e->getMessage()."\n";
					echo $e->getTraceAsString()."\n";
				}
			}
			else {
				$passed += 1;
			}
		}
		$this->endTime = microtime(true);
		$this->runTime = $this->endTime - $this->startTime;
		echo "\nFinished in ".number_format($this->runTime, 4)." seconds\n\n";
		echo $this->color('Passed: ', 'bold');
		echo $this->color($passed, 'green');
		echo $this->color(' Failed: ', 'bold');
		echo $this->color($failed, 'red');
		echo "\n\n";
	}
}

function describe($name, $body) {
	return Runner::instance()->describe($name, $body);
}

function it($description, $body) {
	return Runner::instance()->it($description, $body);
}

function expect($actual) {
	return Runner::instance()->expect($actual);
}

function run($formatter=null) {
	return Runner::instance()->run($formatter);
}
