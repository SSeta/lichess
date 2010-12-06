<?php

namespace Bundle\LichessBundle\Storage;

interface StorageInterface {

    function store($key, $data, $ttl = null);

    function get($key);

    function delete($key);

    function getIterator($regex);
}
