<?php

namespace ElSchneider\StatamicSimpleAddress\Providers;

class ProviderRegistry
{
    private static ?array $providers = null;

    /**
     * Get all built-in provider class mappings.
     *
     * @return array<string, class-string<AbstractProvider>>
     */
    public static function all(): array
    {
        if (self::$providers !== null) {
            return self::$providers;
        }

        self::$providers = [];
        $directory = __DIR__;

        foreach (glob("{$directory}/*Provider.php") as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);

            // Skip the abstract base class
            if ($className === 'AbstractProvider') {
                continue;
            }

            $fqcn = __NAMESPACE__.'\\'.$className;

            // Verify it's a valid provider class
            if (! class_exists($fqcn) || ! is_subclass_of($fqcn, AbstractProvider::class)) {
                continue;
            }

            // Derive provider name: GeoapifyProvider -> geoapify
            $name = strtolower(preg_replace('/Provider$/', '', $className));
            self::$providers[$name] = $fqcn;
        }

        return self::$providers;
    }

    /**
     * Get list of available provider names.
     *
     * @return string[]
     */
    public static function names(): array
    {
        return array_keys(self::all());
    }

    /**
     * Check if a provider exists.
     */
    public static function has(string $name): bool
    {
        return isset(self::all()[$name]);
    }

    /**
     * Get a provider class by name.
     *
     * @return class-string<AbstractProvider>|null
     */
    public static function get(string $name): ?string
    {
        return self::all()[$name] ?? null;
    }

    /**
     * Create a provider instance by name.
     */
    public static function make(string $name, array $config = []): AbstractProvider
    {
        $class = self::get($name);

        if ($class === null) {
            throw new \InvalidArgumentException(
                "Provider '{$name}' not found. Available: ".implode(', ', self::names())
            );
        }

        return new $class($config);
    }

    /**
     * Reset the cached providers (useful for testing).
     */
    public static function reset(): void
    {
        self::$providers = null;
    }
}
