<?php

namespace pecs;

$code = <<<'EOC'
function after_each($func) {
   return \pecs\runner()->after_each($func);
}
function before_each($func) {
   return \pecs\runner()->after_each($func);
}
function describe($description, $func) {
   return \pecs\runner()->describe($description, $func);
}
function it($description, $func) {
   return \pecs\runner()->it($description, $func);
}
function expect($actual) {
   return \pecs\runner()->expect($actual);
}
EOC;
// eval is the only way to execute within global namespace
if (@constant('\PECS_GLOBALS') !== false) eval($code); // global ns aliases
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
      $this->hooks = array();
      $this->spec = null;
      $this->specs = array();
      $this->suite = null;
      $this->suites = array();
   }
   
   function after_each($func) {
      $this->hooks['after_each'][$this->suite->id] = $func;
   }
   
   function before_each($func) {
      $this->hooks['before_each'][$this->suite->id] = $func;
   }
   
   function describe($description, $func) {
      $suite = new Suite($description, $func, $this->suite);
      $this->suites[] = $suite;
      $this->suite = $suite;
      $func();
      $this->suite = $suite->parent;
      return $suite;
   }

   function it($description, $func) {
      $spec = new Spec($description, $func, $this->suite);
      $this->specs[] = $spec;
      return $spec;
   }

   function expect($actualValue) {
      return new Expect($actualValue, $this->spec);
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
            $spec->run();
            $this->formatter->afterSpec($spec);
         }
         $this->formatter->afterSuite($suite);
      }
      $this->formatter->after();
   }
}

/// Contains one or more specs.
class Suite {
   function __construct($description=null, $func=null, $parent=null) {
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
   
   function push($child) {
      $child instanceof Spec ? $this->specs[]=$child : $this->suites[]=$child;
   }
}

/// An actual test case.
class Spec extends Suite {
   public $failures = array();
   
   function __construct($description=null, $func=null, $parent=null) {
      parent::__construct($description, $func, $parent);
      $this->description = $description;
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
   
   function run() {
      $func = $this->func;
      try {
         $func();
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
      $values = (array)call_user_func_array(array($this, $method), $args);
      $result = array_shift($values);
      if ($result != $expectedResult)
         $this->_fail($method, $args, $result, $expectedResult, $values);
   }
   
   function _fail($method, $args, $result, $expectedResult, $values) {
      if (empty($values)) {
         $format = 'expected %s to '.str_replace('_', ' ', $method).' %s';
         $values = array_merge(array($this->actual), $args);
      }
      else
         $format = array_shift($values);
      array_walk($values, function(&$v) { $v = var_export($v, true); });
      $this->spec->fail(new \Exception(vsprintf($format, $values)));
   }
   
   /// Matcher aliases
   public $_aliases = array(
      'be' => 'equal',
      'be_an' => 'be_a',
      'have_count' => 'have_length',
      'have_count_within' => 'have_length_within',
      'throw' => 'throw_error',
   );
   
   /// New matchers can be defined by adding a function below.
   
   function equal($expected) {
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
      return $this->actual >= $min && $this->actual <= $max;
   }
   
   function be_a($expected) {
      $class = get_class($this->actual);
      return array(
         $class === $expected,
         'expected %s to be class %s', $class, $expected);
   }
   
   function be_an_instance_of($expected) {
      $class = get_class($this->actual);
      return array(
         $this->actual instanceof $expected,
         'expected %s to be an instance of %s, was %s',
         $this->actual, $expected, $class);
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
      $type = gettype($this->actual);
      return array(
         $type === $expected,
         'expected %s to be type %s, was %s',
         $this->actual, $expected, $type);
   }
   
   function have_length($expected) {
      $n = is_string($this->actual) ? strlen($this->actual) : count($this->actual);
      return $n === $expected;
   }
   
   function have_length_within($min, $max) {
      $n = is_string($this->actual) ? strlen($this->actual) : count($this->actual);
      return array(
         $n >= $min && $n <= $max, $n,
         'expected %s to have count within %d and %d, was %d',
         $this->actual, $min, $max, $n);
   }
   
   function throw_error($className=null, $message=null) {
      try {
         $func = $this->actual;
         $func();
         return false;
      }
      catch (\Exception $e) {
         if ((!$className || $e instanceof $className) &&
             (!$message || $e->getMessage() == $message)) {
            return true;
         }
         throw $e;
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
            $failed += 1;
            foreach ($spec->failures as $failure) {
               echo "\nFAILURE:\n";
               echo $failure->getMessage()."\n";
               echo $failure->getTraceAsString()."\n";
            }
         }
         else
            $passed += 1;
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

