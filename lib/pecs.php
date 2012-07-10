<?php

namespace pecs;

function errorToException($errno, $errstr, $errfile, $errline, $errcontext) {
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
}
set_error_handler('\pecs\errorToException', E_ALL);

$code = <<<'EOC'
function after_each($func) {
    return \pecs\runner()->suite->hook('after_each', $func);
}
function before_each($func) {
    return \pecs\runner()->suite->hook('before_each', $func);
}
function describe($description, $func) {
    return \pecs\runner()->describe($description, $func);
}
function it($description, $func) {
    return \pecs\runner()->it($description, $func);
}
function expect($actual) {
    return \pecs\runner()->spec->expect($actual);
}
EOC;
// eval is the only way to execute within global namespace
if (!defined('\PECS_GLOBALS') || constant('\PECS_GLOBALS') !== false)
    eval($code); // global ns aliases
eval("namespace pecs;\n$code"); // local ns aliases

/// Run the tests.
function run($formatter=null) {
    return runner()->run($formatter);
}

/// Return the Runner singleton, or set it to a new object.
function runner($newRunner=null) {
    static $runner=null;
    if (!$runner || $newRunner)
        $runner = $newRunner ?: new Runner();
    return $runner;
}

/// Keeps track of things and does the heavy lifting.
class Runner {
    function __construct() {
        $this->formatter = new Formatter();
        $this->hooks = array('after_each'=>array(), 'before_each'=>array());
        $this->spec = null;
        $this->specs = array();
        $this->suite = null;
        $this->suites = array();
    }
    
    function describe($description, $func) {
        $suite = new Suite($description, $func, $this->suite);
        $this->suites[] = $suite;
        $this->suite = $suite;
        $func();
        $this->suite = $suite->parent;
        return $suite;
    }

    function hook($hook, $func) {
        if (!isset($this->hooks[$hook]))
            $this->hooks[$hook] = array();
        $this->hooks[$hook][] = $func;
    }
    
    function it($description, $func) {
        $spec = new Spec($description, $func, $this->suite);
        $this->specs[] = $spec;
        return $spec;
    }

    function run($formatter=null) {
        if (!is_null($formatter))
            $this->formatter = $formatter;
        $this->formatter->before();
        foreach ($this->suites as $suite) {
            $this->formatter->beforeSuite($suite);
            foreach ($suite->specs as $spec) {
                $this->spec = $spec;
                $this->formatter->beforeSpec($spec);
                $scope = $spec->runHooks('before_each');
                $spec->run($scope);
                $spec->runHooks('after_each', $scope);
                $this->formatter->afterSpec($spec);
            }
            $this->formatter->afterSuite($suite);
        }
        $this->formatter->after();
    }

    function runHooks($hook, $scope=array()) {
        if (isset($this->hooks[$hook])) {
            foreach ($this->hooks[$hook] as $func) {
                $newScope = $func($scope);
                if (!is_null($newScope))
                    $scope = $newScope;
            }
        }
        return $scope;
    }
}

/// Contains one or more specs.
class Suite {
    function __construct($description=null, $func=null, $parent=null) {
        $this->hooks = array();
        $this->id = spl_object_hash($this);
        $this->specs = array();
        $this->suites = array();
        $this->func = $func;
        if ($parent) {
            $this->description = trim($parent->description.' '.$description);
            $this->parent = $parent;
            $this->parent->push($this);
        }
        else {
            $this->description = $description;
            $this->parent = null;
        }
    }
    
    function hook($hook, $func) {
        if (!isset($this->hooks[$hook]))
            $this->hooks[$hook] = array();
        $this->hooks[$hook][] = $func;
    }
    
    function parents() {
        $parents = array();
        for ($parent=$this->parent; $parent; $parent=$parent->parent) {
            array_unshift($parents, $parent);
        }
        return $parents;
    }
    
    function push($child) {
        $child instanceof Spec ? $this->specs[]=$child : $this->suites[]=$child;
    }

    function runHooks($hook, $scope=array()) {
        if ($this->parent)
            $scope = $this->parent->runHooks($hook, $scope);
        if (isset($this->hooks[$hook])) {
            foreach ($this->hooks[$hook] as $func) {
                $newScope = $func($scope, $this);
                if (!is_null($newScope))
                    $scope = $newScope;
            }
        }
        return $scope;
    }   
}

/// An actual test case.
class Spec extends Suite {
    public $assertions = 0;
    public $failures = array();
    
    function __construct($description=null, $func=null, $parent=null) {
        parent::__construct($description, $func, $parent);
        $this->description = $description;
    }
    
    function expect($actualValue) {
        return new Expect($actualValue, $this);
    }
    
    function fail($failure) {
        if (is_string($failure))
            $failure = new \Exception($failure);
        $this->failures[] = $failure;
    }
    
    function failed() {
        return !$this->passed();
    }
    
    function passed() {
        return empty($this->failures);
    }
    
    function run($scope=array()) {
        $func = $this->func;
        try {
            $func($scope, $this);
        }
        catch (\Exception $e) {
            $this->fail($e);
        }
    }
}

/// This is the sauce. expect($foo) returns one of these objects, and the
/// __call handler transforms the spec style ->not_to_be_false() into a call
/// to appropriate method.
class Expect {
    function __construct($actual, $spec) {
        $this->actual = $actual;
        $this->spec = $spec;
    }
    
    function __call($method, $args) {
        $expectedResult = true;
        if (preg_match('/^(and_)?(not_)?(to_)?_*(.+)$/', $method, $matches)) {
            $method = $matches[4];
            $expectedResult = empty($matches[2]);
        }
        if (isset($this->_aliases[$method]))
            $method = $this->_aliases[$method];
        if (!method_exists($this, $method))
            throw new \Exception("Unknown expectation assertion \"{$method}\"");
        $this->_assert($method, $args, $expectedResult);
        return $this;
    }
    
    function _assert($method, $args, $expectedResult) {
        $this->spec->assertions += 1;
        $values = (array)call_user_func_array(array($this, $method), $args);
        $result = array_shift($values);
        if ($result != $expectedResult)
            $this->_fail($method, $args, $result, $expectedResult, $values);
    }
    
    function _fail($method, $args, $result, $expectedResult, $values) {
        if (empty($values)) {
            $format = 'expected %s to '.str_replace('_', ' ', $method);
            $values = array($this->actual);
            if (!empty($args)) {
                $format .= ' %s';
                $values[] = $args[0];
            }
        }
        else
            $format = array_shift($values);
        if (!empty($values)) {
            array_walk($values, function(&$v) { $v = Expect::_export($v); });
            $message = vsprintf($format, $values);
        }
        else
            $message = $format;
        $this->spec->fail(new \Exception($message));
    }
    
    static function _export($var) {
        if (is_array($var)) {
            $pairs = array();
            foreach ($var as $key=>$value)
                $pairs[] = var_export($key, true).' => '.static::_export($value);
            return 'array('.implode(', ', $pairs).')';
        }
        else if (is_null($var)) {
            return 'null';
        }
        else if (is_object($var)) {
            $var = var_export($var, true);
            return preg_replace(array('~array\(\s*\)~', '~::__set_state~'),
                                array('array()', ''), $var);
        }
        else {
            return var_export($var, true);
        }
    }
    
    /// Matcher aliases
    public $_aliases = array(
        'be_an' => 'be_a',
        'equal' => 'be',
        'have_count' => 'have_length',
        'have_count_within' => 'have_length_within',
        'throw' => 'throw_error',
    );
    
    /// New matchers can be defined by adding a function below.
    
    function be($expected) {
        return $this->actual === $expected;
    }
    
    function be_greater_than($expected) {
        return $this->actual > $expected;
    }
    
    function be_less_than($expected) {
        return $this->actual < $expected;
    }
    
    function be_at_least($expected) {
        return $this->actual >= $expected;
    }
    
    function be_at_most($expected) {
        return $this->actual <= $expected;
    }
    
    function be_within($min, $max) {
        return array(
            $this->actual >= $min && $this->actual <= $max,
            'expected %s to be within %s and %s', $this->actual, $min, $max);
    }
    
    function be_a($expected) {
        $class = get_class($this->actual);
        return array(
            $class === $expected,
            "expected $class to be class $expected");
    }
    
    function be_an_instance_of($expected) {
        $class = get_class($this->actual);
        return array(
            $this->actual instanceof $expected,
            "expected $class to be an instance of $expected");
    }
    
    function be_null() {
        return is_null($this->actual);
    }
    
    function be_true() {
        return $this->actual === true;
    }
    
    function be_false() {
        return $this->actual === false;
    }
    
    function be_empty() {
        return empty($this->actual);
    }
    
    function be_type($expected) {
        $expected = strtolower($expected);
        $type = strtolower(gettype($this->actual));
        return array(
            $type === $expected,
            "expected %s to be type $expected, was $type", $this->actual);
    }
    
    function have_length($expected) {
        if (is_string($this->actual))
            $length = strlen($this->actual);
        else
            $length = count($this->actual);
        return array(
            $length === $expected,
            'expected %s to have length %d, was %d',
            $this->actual, $expected, $length);
    }
    
    function have_length_within($min, $max) {
        if (is_string($this->actual))
            $length = strlen($this->actual);
        else
            $length = count($this->actual);
        return array(
            $length >= $min && $length <= $max,
            'expected %s to have length within %d and %d, was %d',
            $this->actual, $min, $max, $length);
    }
    
    function throw_error($className=null, $message=null) {
        try {
            $func = $this->actual;
            $func();
            return array(
                false,
                $className ?
                    "expected $className to be thrown, but was not" :
                    'expected exception to be thrown, but was not');
        }
        catch (\Exception $e) {
            if ($className && !($e instanceof $className)) {
                $actualClassName = get_class($e);
                return array(
                    false,
                    "expected $className to be thrown, " .
                    "but got $actualClassName instead");
            }
            if ($message && $e->getMessage() != $message) {
                return array(
                    false,
                    "expected thrown exception to have message %s, " .
                    "but had message %s",
                    $message, $e->getMessage());
            }
            return true;
        }
    }
}

class Failure extends \Exception {
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
        if ($suite instanceof Runner)
            return;
        if (!empty($suite->specs))
            echo $this->color("\n{$suite->description}\n", 'bold');
    }
    
    function beforeSpec($spec) {
        echo "- {$spec->description}: ";
    }
    
    function afterSpec($spec) {
        if ($spec->passed())
            echo $this->color("pass\n", 'green');
        else
            echo $this->color("fail   \n", 'red');
    }
    
    function afterSuite($suite) {
    }
    
    function after() {
        $passed = $failed = 0;
        foreach (runner()->specs as $spec) {
            if ($spec->failed()) {
                $count = count($spec->failures);
                $failed += $count;
                $passed += $spec->assertions - $count;
                foreach ($spec->failures as $failure) {
                    echo "\nFAILURE:\n";
                    echo $failure->getMessage()."\n";
                    echo $failure->getTraceAsString()."\n";
                }
            }
            else
                $passed += $spec->assertions;
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

class HtmlFormatter extends Formatter
{
  static $colors = array(
    'black' => '#000000',
    'white' => '#ffffff',
    'red'   => '#ff2222',
    'green' => '#33ee33',
    'blue'  => '#0000ff'
  );
  
  function color($string, $color)
  {
    if($color == 'bold')
      $style = "font-weight: bold;";
    else 
      $style = "color:". self::$colors[$color];
    $ret = "<span style=\"{$style}\">{$string}</span>";
    $ret = nl2br($ret);
    return $ret;
  }

   function after() {
      $passed = $failed = 0;
      foreach (runner()->specs as $spec) {
          if ($spec->failed()) {
              $count = count($spec->failures);
              $failed += $count;
              $passed += $spec->assertions - $count;
              foreach ($spec->failures as $failure) {
                  echo nl2br("\nFAILURE:\n");
                  echo nl2br($failure->getMessage()."\n");
                  echo nl2br($failure->getTraceAsString()."\n");
              }
          }
          else
              $passed += $spec->assertions;
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
