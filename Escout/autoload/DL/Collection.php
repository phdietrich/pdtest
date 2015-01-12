<?php

namespace DL;

class Collection implements \Countable, \IteratorAggregate, \JsonSerializable {

	const MODEL = '\stdClass';
	protected $collection = array();

	public static function __set_state ($states) {
		$self = new static;

		$class = $self::MODEL;
		foreach ($states as $state) {
			$model = $class::__set_state($state);
			$self->add($model);
		}

		return $self;
	}

	public function __construct () {}

	public function add ($model) {
		$id = $this->getHash($model);
		$this->collection[$id] = $model;

		return $model;
	}

	public function exists ($model) {
		$id = $this->getHash($model);
		return array_key_exists($id, $this->collection);
	}

	public function remove ($model) {
		$id = $this->getHash($model);
		unset($this->collection[$id]);
	}

	public function getHash ($model) {
		return spl_object_hash($model);
	}


	// interface Countable
	public function count() {
		return count($this->collection);
	}

	// interface IteratorAggregate
	public function getIterator() {
		return new \ArrayIterator($this->collection);
	}

	// interface JsonSerializable
	public function jsonSerialize ($values_only = false) {
		$json = array();

		foreach ($this->collection as $model) {
			$json[] = $model->jsonSerialize($values_only);
		}

		return $json;
	}
}