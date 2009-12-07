<?php

namespace pecs;

// vomit worthy, but no other way to define in
// the global namespace without a second file
$code = <<<'EOC'
function describe($text, $func) {
   return \pecs\runner()->describe($text, $func);
}
function it($text, $func) {
   return \pecs\runner()->it($text, $func);
}
function expect($actual) {
   return \pecs\runner()->expect($actual);
}
EOC;
if (getenv('PECS_NO_GLOBALS') != '1') eval($code); // global aliases
eval("namespace pecs;\n$code"); // local aliases

function run($formatter=null) {
   return runner()->run($formatter);
}

function runner($newRunner=null) {
   static $runner=null;
   if (!$runner || $newRunner) {
      $runner = $newRunner ?: new Runner();
   }
   return $runner;
}

class Runner {
   function __construct() {
      $this->formatter = new Formatter();
      $this->spec = null;
      $this->specs = array();
      $this->suite = null;
      $this->suites = array();
   }
   
   function describe($description, $body) {
      $suite = new Suite($description, $body, $this->suite);
      $this->suites[] = $suite;
      $this->suite = $suite;
      $body();
      $this->suite = $suite->parent;
      return $suite;
   }

   function it($description, $body) {
      $spec = new Spec($description, $body, $this->suite);
      $this->specs[] = $spec;
      return $spec;
   }

   function expect($actualValue) {
      return new Expect($actualValue, $this->spec);
   }

   function run($formatter=null) {
      if (!is_null($formatter)) {
         $this->formatter = $formatter;
      }
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

class Suite {
   function __construct($description=null, $body=null, $parent=null) {
      $this->specs = array();
      $this->suites = array();
      $this->body = $body;
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

class Spec extends Suite {
   public $exceptions = array();
   
   function __construct($description, $body, $parent) {
      parent::__construct($description, $body, $parent);
      $this->description = $description;
   }
   
   function fail($exception) {
      if (is_string($exception)) {
         $exception = new \Exception($exception);
      }
      $this->exceptions[] = $exception;
   }
   
   function failed() {
      return !$this->passed();
   }
   
   function passed() {
      return empty($this->exceptions);
   }
   
   function run() {
      $body = $this->body;
      try {
         $body();
      }
      catch (\Exception $e) {
         $this->fail($e);
      }
   }
}

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
      if (!method_exists($this, $method)) {
         throw new \Exception("Unknown assertion \"{$method}\"");
      }
      $this->_assert($method, $args, $expectedResult);
      return $this;
   }
   
   function _assert($method, $args, $expectedResult) {
      $values = (array)call_user_func_array(array($this, $method), $args);
      $result = array_shift($values);
      if ($result != $expectedResult) {
         $this->_fail($method, $args, $result, $expectedResult, $values);
      }
   }
   
   function _fail($method, $args, $result, $expectedResult, $values) {
      if (empty($values)) {
         $format = 'expected %s to '.str_replace('_', ' ', $method).' %s';
         $values = array_merge(array($this->actual), $args);
      }
      else {
         $format = array_shift($values);
      }
      array_walk($values, function(&$v) { $v = var_export($v, true); });
      $this->spec->fail(new \Exception(vsprintf($format, $values)));
   }
   
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
      return array(
         $type === $expected,
         'expected %s to be type %s, was %s',
         $this->actual, $expected, $type);
   }
   
   function have_count($expected) {
      return count($this->actual) === $expected;
   }
   
   function have_count_within($min, $max) {
      $count = count($this->actual);
      return array(
         $count >= $min && $count <= $max, $count,
         'expected %s have count within %d and %d, was %d',
         $this->actual, $min, $max, $count);
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
      if ($suite instanceof Runner) return;
      if (!empty($suite->specs)) {
         echo $this->color("\n{$suite->description}\n", 'bold');
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
         echo $this->color("fail   \n", 'red');
      }
   }
   
   function afterSuite($suite) {
   }
   
   function after() {
      $passed = $failed = 0;
      foreach (runner()->specs as $spec) {
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

