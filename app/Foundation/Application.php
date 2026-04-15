<?php

namespace App\Foundation;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Foundation\ProviderRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;
use Throwable;

class Application extends LaravelApplication
{
    /**
     * Ignora providers que não são classes concretas de ServiceProvider carregáveis
     * (pacotes dev ausentes em --no-dev, plug-and-play opcional, etc.).
     */
    private static function isLoadableServiceProvider(mixed $provider): bool
    {
        if (! is_string($provider)) {
            return true;
        }

        if (! is_subclass_of($provider, ServiceProvider::class, true)) {
            return false;
        }

        try {
            return (new ReflectionClass($provider))->isInstantiable();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function registerConfiguredProviders()
    {
        $providers = (new Collection($this->make('config')->get('app.providers')))
            ->partition(fn ($provider) => str_starts_with($provider, 'Illuminate\\'));

        $packageProviders = array_values(array_filter(
            $this->make(PackageManifest::class)->providers(),
            static fn ($provider) => self::isLoadableServiceProvider($provider)
        ));

        $providers->splice(1, 0, [$packageProviders]);

        $merged = $providers->collapse()
            ->filter(static fn ($provider) => self::isLoadableServiceProvider($provider))
            ->values()
            ->toArray();

        (new ProviderRepository($this, new Filesystem, $this->getCachedServicesPath()))
            ->load($merged);

        $this->fireAppCallbacks($this->registeredCallbacks);
    }
}
