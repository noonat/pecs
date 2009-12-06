work it.
========

pecs is a tiny BDD library for PHP 5.3.

To use the [traditional example](http://rspec.info), it works like this:

    require "lib/pecs.php";
    require "bowling.php";
    
    describe("Bowling", function() {
      it("should score 0 for a gutter game", function() {
        $bowling = new Bowling();
        for ($i=0; $i < 20; $i++) {
          expect($bowling->score)->to_equal(0);
        }
      });
    });

And the output looks something like this:

    $ php test_bowling.php 

    Bowling
    - should score 0 for a gutter game: pass

    Finished in 0.0017 seconds

    Passed: 1 Failed: 0

pecs is greatly inspired by [JSpec](http://github.com/visionmedia/jspec). The
only reason pecs exists is because I got tired of switching between PHPUnit and
JSpec style tests. GO JSPEC!

License
=======

(The MIT License)

Copyright ©2009 Nathan Ostgard <no@nathanostgard.com>

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the ‘Software’), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED ‘AS IS’, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
