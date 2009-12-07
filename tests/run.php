<?php

require __DIR__.'/../lib/pecs.php';

// include the tests
require __DIR__.'/pecs/expectation.php';
require __DIR__.'/pecs/runner.php';
require __DIR__.'/pecs/spec.php';
require __DIR__.'/pecs/suite.php';

// run 'em
\pecs\run();
