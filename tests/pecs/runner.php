<?php

class MockRunner extends pecs\Runner {
    function __construct() {
        parent::__construct();
        $this->runCalls = array();
    }
    
    function run() {
        $this->runCalls[] = func_get_args();
    }
}

describe("pecs", function() {
    // FIXME:
    // after_each(function($spec, $runner) {
    //    // restore the old runner, in case we stomped on things
    //    runner($runner);
    // });
    
    describe("runner", function() {
        it("should return the current runner object", function() {
            $runner = pecs\runner();
            expect($runner)->to_be_a('pecs\Runner');
        });
        
        it("should only instantiate one runner", function() {
            $runner = pecs\runner();
            expect($runner)->to_be_a('pecs\Runner');
            expect(pecs\runner())->to_be($runner);
        });
        
        it("should allow you to pass a new pecs\Runner", function($scope, $spec) {
            $oldRunner = pecs\runner();
            $newRunner = new pecs\Runner();
            $runner = pecs\runner($newRunner);
            $spec->expect($runner)->to_be($newRunner);
            $spec->expect(pecs\runner())->to_be($runner);
            pecs\runner($oldRunner);
        });
    });
    
    describe("Runner", function() {
        it("shouldn't have any specs or suites by default", function() {
            $runner = new pecs\Runner();
            expect($runner->suites)->to_be_type('array')->and_to_be_empty();
            expect($runner->specs)->to_be_type('array')->and_to_be_empty();
            expect($runner->suite)->to_be_null();
            expect($runner->spec)->to_be_null();
        });
        
        it("should create a default formatter", function() {
            $runner = new pecs\Runner();
            expect($runner->formatter)->to_be_an_instance_of('pecs\Formatter');
        });
        
        describe("describe()", function() {
            it("should create a suite", function() {
                $func = function() {};
                $runner = new pecs\Runner();
                $suite = $runner->describe('foo', $func);
                expect($suite)->to_be_a('pecs\Suite');
                expect($suite->description)->to_be('foo');
                expect($suite->func)->to_be($func);
            });
            
            it("should add the suite to the list of suites", function() {
                $runner = new pecs\Runner();
                expect($runner->suites)->to_be_empty();
                $suite1 = $runner->describe("foo", function() {});
                expect($runner->suites)->to_have_count(1);
                expect($runner->suites[0])->to_be($suite1);
                $suite2 = $runner->describe("bar", function() {});
                expect($runner->suites)->to_have_count(2);
                expect($runner->suites[0])->to_be($suite1);
                expect($runner->suites[1])->to_be($suite2);
            });
            
            it("should call the suite's function once", function() {
                $called = 0;
                $runner = new pecs\Runner();
                $runner->describe('foo', function() use(&$called) { $called += 1; });
                expect($called)->to_be(1);
            });
            
            it("should set \$runner->suite to the suite before calling", function() {
                $runner = new pecs\Runner();
                $funcSuite = null;
                $suite = $runner->describe('foo', function() use(&$funcSuite, $runner) {
                    $funcSuite = $runner->suite;
                });
                expect($funcSuite)->to_be($suite);
            });
            
            it("should restore \$runner->suite after calling", function() {
                $runner = new pecs\Runner();
                expect($runner->suite)->to_be_null();
                $runner->describe('foo', function() {});
                expect($runner->suite)->to_be_null();
            });
        });
        
        describe("it()", function() {
            it("should create a spec", function() {
                $func = function() {};
                $runner = new pecs\Runner();
                $spec = $runner->it('foo', $func);
                expect($spec)->to_be_a('pecs\Spec');
                expect($spec->description)->to_be('foo');
                expect($spec->func)->to_be($func);
            });
            
            it("should add the spec to the list of specs", function() {
                $runner = new pecs\Runner();
                expect($runner->specs)->to_be_empty();
                $spec1 = $runner->it("foo", function() {});
                expect($runner->specs)->to_have_count(1);
                expect($runner->specs[0])->to_be($spec1);
                $spec2 = $runner->it("bar", function() {});
                expect($runner->specs)->to_have_count(2);
                expect($runner->specs[0])->to_be($spec1);
                expect($runner->specs[1])->to_be($spec2);
            });
            
            it("should set the spec's parent to the current suite", function() {
                $suite = new pecs\Suite();
                $runner = new pecs\Runner();
                $runner->suite = $suite;
                $spec = $runner->it('foo', function() {});
                expect($spec->parent)->to_be($suite);
            });
            
            it("should not call the spec's function", function() {
                $called = 0;
                $func = function() use(&$called) { $called += 1; };
                $runner = new pecs\Runner();
                $runner->it('foo', $func);
                expect($called)->to_be(0);
            });
        });
        
        describe("run", function() {
            // FIXME
            /*
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
            */
        });
    });
    
    describe("run", function() {
        it("should call run on the current runner object", function($scope, $spec) {
            $oldRunner = pecs\runner();
            $mockRunner = new MockRunner();
            pecs\runner($mockRunner);
            $spec->expect($mockRunner->runCalls)->to_be_empty();
            $formatter = new pecs\Formatter();
            pecs\run($formatter);
            pecs\run($formatter);
            $spec->expect($mockRunner->runCalls)->to_have_count(2);
            $spec->expect($mockRunner->runCalls[0])->to_have_count(1);
            $spec->expect($mockRunner->runCalls[1])->to_have_count(1);
            $spec->expect($mockRunner->runCalls[0][0])->to_be($formatter);
            $spec->expect($mockRunner->runCalls[1][0])->to_be($formatter);
            pecs\runner($oldRunner);
        });
    });
});
