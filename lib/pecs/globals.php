<?php

function describe($name, $body) {
	return pecs\Runner::instance()->describe($name, $body);
}

function it($description, $body) {
	return pecs\Runner::instance()->it($description, $body);
}

function expect($actual) {
	return pecs\Runner::instance()->expect($actual);
}
