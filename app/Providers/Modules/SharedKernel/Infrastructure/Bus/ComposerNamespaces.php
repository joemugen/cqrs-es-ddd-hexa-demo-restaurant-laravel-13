<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Bus;

use Composer\Autoload\ClassLoader;

/**
 * Answers "what is in this namespace" from the PSR-4 map of Composer, so no code spells a path.
 */
final class ComposerNamespaces
{
    /**
     * @return list<class-string>
     */
    public function classesIn(string $namespace): array
    {
        $classes = [];

        foreach (glob($this->directoryOf($namespace).'/*.php') ?: [] as $file) {
            $class = $namespace.'\\'.basename($file, '.php');

            if (class_exists($class) || interface_exists($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * @return list<string>
     */
    public function namespacesUnder(string $namespace): array
    {
        $directories = glob($this->directoryOf($namespace).'/*', GLOB_ONLYDIR) ?: [];

        return array_map(fn (string $directory): string => $namespace.'\\'.basename($directory), $directories);
    }

    private function directoryOf(string $namespace): string
    {
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            foreach ($loader->getPrefixesPsr4() as $prefix => $directories) {
                $directory = $this->directoryUnder($prefix, $directories, $namespace);

                if ($directory !== null) {
                    return $directory;
                }
            }
        }

        return '/nonexistent/'.$namespace;
    }

    /**
     * @param  list<string>  $directories
     */
    private function directoryUnder(string $prefix, array $directories, string $namespace): ?string
    {
        if (! str_starts_with($namespace.'\\', $prefix)) {
            return null;
        }

        foreach ($directories as $directory) {
            $candidate = $directory.'/'.str_replace('\\', '/', trim(substr($namespace.'\\', strlen($prefix)), '\\'));

            if (is_dir($candidate)) {
                return rtrim($candidate, '/');
            }
        }

        return null;
    }
}
