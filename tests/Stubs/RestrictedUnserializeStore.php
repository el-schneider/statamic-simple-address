<?php

namespace Tests\Stubs;

use Illuminate\Cache\ArrayStore;

/**
 * Mimics Laravel 13's `serializable_classes = false`, which is what broke caching in the
 * first place: whatever goes in comes back as plain data, objects are gone.
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

    /** Everything held in the cache, as written. A serialized object names its class here. */
    public function serialized(): string
    {
        return implode('', array_column($this->storage, 'value'));
    }
}
