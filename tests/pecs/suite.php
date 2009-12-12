<?php

use pecs\Spec as Spec;
use pecs\Suite as Suite;

describe("pecs", function() {
    describe("Suite", function() {
        it("should have an id", function() {
            $suite = new Suite();
            expect($suite->id)->not_to_be_empty()->and_to_be_type('string');
        });
        
        it("shouldn't have the same id as another object", function() {
            $suite1 = new Suite();
            $suite2 = new Suite();
            expect($suite1->id)->not_to_be_empty()->and_to_be_type('string');
            expect($suite2->id)->not_to_be_empty()->and_to_be_type('string');
            expect($suite1->id)->not_to_equal($suite2->id);
        });
        
        it("should start with an empty set of specs and suites", function() {
            $suite = new Suite();
            expect($suite->specs)->to_be_empty();
            expect($suite->suites)->to_be_empty();
        });
        
        it("should have a description", function() {
            $suite = new Suite('foo');
            expect($suite->description)->to_be('foo');
        });
        
        it("should append onto the parent's description", function() {
            $parent = new Suite('foo');
            $child = new Suite('bar', null, $parent);
            expect($parent->description)->to_be('foo');
            expect($child->description)->to_be('foo bar');
        });
        
        it("should push child suites into parent suites", function() {
            $parent = new Suite();
            $child = new Suite(null, null, $parent);
            expect($parent->specs)->to_be_empty();
            expect($parent->suites)->to_have_count(1);
            expect($parent->suites[0])->to_be($child);
        });
        
        it("should push child specs into parent suites", function() {
            $parent = new Suite();
            $child = new Spec(null, null, $parent);
            expect($parent->specs)->to_have_count(1);
            expect($parent->specs[0])->to_be($child);
            expect($parent->suites)->to_be_empty();
        });
    });
});
