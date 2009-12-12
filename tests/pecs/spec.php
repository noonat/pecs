<?php

describe("pecs", function() {
    describe("Spec", function() {
        it("should be a subclass of suite", function() {
            $spec = new pecs\Spec();
            expect($spec)->to_be_an_instance_of('pecs\Suite');
        });
        
        it("should have a description", function() {
            $spec = new pecs\Spec('foo');
            expect($spec->description)->to_be('foo');
        });
        
        it("should not include the suite's description", function() {
            $suite = new pecs\Suite('foo');
            $spec = new pecs\Spec('bar', null, $suite);
            expect($spec->description)->to_be('bar');
        });
        
        it("should pass by default", function() {
            $spec = new pecs\Spec();
            expect($spec->passed())->to_be_true();
            expect($spec->failed())->to_be_false();
        });
        
        it("should not pass if fail is called", function() {
            $spec = new pecs\Spec();
            $spec->fail('boat');
            expect($spec->passed())->to_be_false();
            expect($spec->failed())->to_be_true();
        });
        
        it("should allow fail to be called more than once", function() {
            $spec = new pecs\Spec();
            $spec->fail('boat');
            $spec->fail('whale');
            expect($spec->passed())->to_be_false();
            expect($spec->failed())->to_be_true();
            expect($spec->failures)->to_have_count(2);
        });
        
        it("should convert failure strings to exceptions", function() {
            $spec = new pecs\Spec();
            $spec->fail('boat');
            expect($spec->failures)->to_have_count(1);
            $failure = $spec->failures[0];
            expect($failure)->to_be_an_instance_of('Exception');
            expect($failure->getMessage())->to_be('boat');
        });
        
        it("should invoke the function once when run", function() {
            $called = 0;
            $spec = new pecs\Spec(null, function() use(&$called) {
                $called += 1;
            });
            expect($called)->to_be(0);
            $spec->run();
            expect($called)->to_be(1);
            $spec->run();
            expect($called)->to_be(2);
        });
        
        it("should catch exceptions when invoking the function", function() {
            $exception = new Exception('failsauce');
            $spec = new pecs\Spec(null, function() use($exception) {
                throw $exception;
            });
            expect($spec->failures)->to_be_empty();
            expect(function() use($spec) {
                $spec->run();
            })->not_to_throw();
            expect($spec->failures)->to_have_count(1);
            expect($spec->failures[0])->to_be($exception);
        });
        
        it("should return a new expect object", function() {
            $spec = new pecs\Spec();
            $expect = $spec->expect('foo');
            expect($expect)->to_be_a('pecs\Expect');
            expect($expect->actual)->to_be('foo');
            expect($expect->spec)->to_be($spec);
        });
    });
});
