magnificent pecs.
=================

pecs is a tiny behavior-driven development library for PHP 5.3, a la
[RSpec](http://github.com/dchelimsky/rspec) or
[JSpec](http://github.com/visionmedia/jspec).

To use the traditional example, it works like this:

    require "lib/pecs.php";
    require "bowling.php";
    
    describe("Bowling", function() {
      it("should score 0 for a gutter game", function() {
        $bowling = new Bowling();
        for ($i=0; $i < 20; $i++) {
          $bowling->hit(0);
        }
        expect($bowling->score)->to_equal(0);
      });
      
      it("should get drinks for free just for being here", function() {
        $bowling = new Bowling();
        expect($bowling->score)->to_equal(42);
      });
    });
    
    \pecs\run();

The output looks something like this:

    $ php test_bowling.php 

    Bowling
    - should score 0 for a gutter game: pass
    - should get drinks for free just for being here: fail	

    FAILURE:
    expected 0 to equal 42
      #0 .../lib/pecs.php(32): pecs\Expect->assert('_equal', Array, true)
      #1 [internal function]: pecs\Expect->__call('to_equal', Array)
      #2 .../test_bowling.php(15): pecs\Expect->to_equal(42)
      #3 .../lib/pecs.php(221): {closure}()
      #4 .../lib/pecs.php(192): pecs\Spec->run()
      #5 .../lib/pecs.php(195): pecs\Suite->run()
      #6 .../lib/pecs.php(195): pecs\Suite->run()
      #7 .../lib/pecs.php(262): pecs\Suite->run()
      #8 .../lib/pecs.php(350): pecs\Runner->run(NULL)
      #9 .../test_bowling.php(19): pecs\run()
      #10 {main}

    Finished in 0.0017 seconds

    Passed: 1 Failed: 1

Credit
======

pecs is greatly inspired by [JSpec](http://github.com/visionmedia/jspec)'s
grammar-less syntax, and was written because I don't want to have to switch
gears so much when going between PHP and JSpec testing.

Todo
====

* Add `before` and `after` hooks
* Need a script to run a folder of tests
* Need to add more matchers
* Need to improve failure output

License
=======

(The MIT License)

Copyright ©2009 noonat

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the ‘Software’), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED ‘AS IS’, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
