<?php

use pecs\Spec as Spec;

describe("pecs", function() {
    describe("Expect", function() {
        it("should pass correct expectations", function() {
            expect(1)->to_be(1);
            expect(null)->to_be(null);
            expect(true)->to_be(true);
            expect(false)->to_be(false);
            expect('test')->to_be('test');
            expect(array())->to_be(array());
            expect(array(1))->to_be(array(1));
            expect(array(1, array(2, 3)))->to_be(array(1, array(2, 3)));
            $object = new stdClass();
            expect($object)->to_be($object);
            expect(null)->to_be_null();
            expect(true)->to_be_true();
            expect(false)->to_be_false();
            expect(0)->to_be_empty();
            expect('')->to_be_empty();
            expect(null)->to_be_empty();
            expect(false)->to_be_empty();
            expect(array())->to_be_empty();
            expect(1)->to_be_greater_than(0);
            expect(1)->to_be_less_than(2);
            expect(1)->to_be_at_least(0);
            expect(1)->to_be_at_least(1);
            expect(1)->to_be_at_most(2);
            expect(1)->to_be_at_most(1);
            expect(1)->to_be_within(1, 1);
            expect(1)->to_be_within(0, 2);
            expect(new pecs\Spec())->to_be_a('pecs\Spec');
            expect(new pecs\Spec())->to_be_an_instance_of('pecs\Suite');
            expect(1)->to_be_type('integer');
            expect(null)->to_be_type('null');
            expect(true)->to_be_type('boolean');
            expect(false)->to_be_type('boolean');
            expect('test')->to_be_type('string');
            expect(array())->to_be_type('array');
            expect(new stdClass())->to_be_type('object');
            expect(array())->to_have_length(0);
            expect(array(1))->to_have_length(1);
            expect(array(1, 2))->to_have_length(2);
            expect('foobar')->to_have_length(6);
            expect(array())->to_have_length_within(0, 0);
            expect(array())->to_have_length_within(-1, 1);
            expect('foobar')->to_have_length_within(6, 6);
            expect('foobar')->to_have_length_within(5, 7);
            expect(function() { throw new Exception; })->to_throw();
            expect(function() { throw new Exception; })->to_throw('Exception');
            expect(function() { throw new Exception('FAIL!'); })
                ->to_throw('Exception');
            expect(function() { throw new Exception('FAIL!'); })
                ->to_throw('Exception', 'FAIL!');
            expect(function() { throw new InvalidArgumentException('FAIL!'); })
                ->to_throw('InvalidArgumentException', 'FAIL!');
        });
        
        it("should fail incorrect expectations", function() {
            $expects = array(
                // all of these should fail
                function($s) { $s->expect(1)->to_be(0); },
                'expected 1 to be 0',
                function($s) { $s->expect(1)->to_be('1'); },
                "expected 1 to be '1'",
                function($s) { $s->expect(1)->to_be(true); },
                'expected 1 to be true',
                function($s) { $s->expect(0)->to_be(null); },
                'expected 0 to be null',
                function($s) { $s->expect(0)->to_be(false); },
                'expected 0 to be false',
                function($s) { $s->expect('foo')->to_be('bar'); },
                "expected 'foo' to be 'bar'",
                function($s) { $s->expect('foo')->to_be('FOO'); },
                "expected 'foo' to be 'FOO'",
                function($s) { $s->expect(array())->to_be(array(1)); },
                "expected array() to be array(0 => 1)",
                function($s) { $s->expect(array(1))->to_be(array(1, 2)); },
                "expected array(0 => 1) to be array(0 => 1, 1 => 2)",
                function($s) { $s->expect(new stdClass())->to_be(new stdClass()); },
                "expected stdClass(array()) to be stdClass(array())",
                function($s) { $s->expect(false)->to_be_null(); },
                'expected false to be null',
                function($s) { $s->expect(false)->to_be_true(); },
                'expected false to be true',
                function($s) { $s->expect(null)->to_be_false(); },
                'expected null to be false',
                function($s) { $s->expect(1)->to_be_empty(); },
                'expected 1 to be empty',
                function($s) { $s->expect(' ')->to_be_empty(); },
                "expected ' ' to be empty",
                function($s) { $s->expect('foo')->to_be_empty(); },
                "expected 'foo' to be empty",
                function($s) { $s->expect(array(null))->to_be_empty(); },
                'expected array(0 => null) to be empty',
                function($s) { $s->expect(new stdClass())->to_be_empty(); },
                'expected stdClass(array()) to be empty',
                function($s) { $s->expect(0)->to_be_greater_than(1); },
                'expected 0 to be greater than 1',
                function($s) { $s->expect(1)->to_be_less_than(0); },
                'expected 1 to be less than 0',
                function($s) { $s->expect(1)->to_be_at_least(2); },
                'expected 1 to be at least 2',
                function($s) { $s->expect(1)->to_be_at_most(0); },
                'expected 1 to be at most 0',
                function($s) { $s->expect(1)->to_be_within(2, 3); },
                'expected 1 to be within 2 and 3',
                function($s) { $s->expect(1)->to_be_within(-1, 0); },
                'expected 1 to be within -1 and 0',
                function($s) {
                    $s->expect(new pecs\Spec())->to_be_a('pecs\Suite'); },
                'expected pecs\Spec to be class pecs\Suite',
                function($s) {
                    $s->expect(new pecs\Spec())
                      ->to_be_an_instance_of('pecs\Runner'); },
                'expected pecs\Spec to be an instance of pecs\Runner',
                function($s) { $s->expect(0)->to_be_type('boolean'); },
                'expected 0 to be type boolean, was integer',
                function($s) { $s->expect('0')->to_be_type('integer'); },
                "expected '0' to be type integer, was string",
                function($s) { $s->expect(null)->to_be_type('integer'); },
                'expected null to be type integer, was null',
                function($s) { $s->expect(array(1, 2))->to_have_length(1); },
                'expected array(0 => 1, 1 => 2) to have length 1, was 2',
                function($s) { $s->expect('foobar')->to_have_length(5); },
                "expected 'foobar' to have length 5, was 6",
                function($s) {
                    $s->expect(array(1, 2))->to_have_length_within(0, 1); },
                'expected array(0 => 1, 1 => 2) to have length within 0 and 1, was 2',
                function($s) {
                    $s->expect('foobar')->to_have_length_within(2, 3); },
                "expected 'foobar' to have length within 2 and 3, was 6",
                function($s) { $s->expect(function() {})->to_throw(); },
                'expected exception to be thrown, but was not',
                function($s) { $s->expect(function() {})->to_throw('Exception'); },
                'expected Exception to be thrown, but was not',
                function($s) {
                    $s->expect(function() {})->to_throw('Exception', 'FAIL!'); },
                'expected Exception to be thrown, but was not',
                function($s) {
                    $s->expect(function() { throw new Exception(); })
                      ->to_throw('LogicException', 'FAIL!'); },
                'expected LogicException to be thrown, but got Exception instead',
                function($s) {
                    $s->expect(function() { throw new LogicException('FIAL!'); })
                      ->to_throw('LogicException', 'FAIL!'); },
                "expected thrown exception to have message 'FAIL!', " .
                "but had message 'FIAL!'",
            );
            foreach (array_chunk($expects, 2) as $expect) {
                list($func, $message) = $expect;
                $spec = new Spec(null, $func);
                $spec->run($spec);
                expect($spec->failures)->to_have_count(1);
                expect($spec->failures[0]->getMessage())->to_be($message);
            }
        });
    });
});
