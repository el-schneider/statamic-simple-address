<?php

namespace Tests\Stubs;

use Illuminate\Cache\ArrayStore;

/**
 * Mimics `serializable_classes = false`: whatever goes in comes back as plain data.
 */
class RestrictedUnserializeStore extends ArrayStore
{
    public function __construct()
    {
        parent::__construct(serializesValues: true);
    }

    public function get($key): mixed
    {
        if (! isset($this->storage[$key])) {
            return null;
        }

        return unserialize($this->storage[$key]['value'], ['allowed_classes' => false]);
    }

    /** The cache contents as written — a serialized object names its class here. */
    public function serialized(): string
    {
        return implode('', array_column($this->storage, 'value'));
    }
}
