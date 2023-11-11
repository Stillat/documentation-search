<?php

namespace Stillat\DocumentationSearch;

use Statamic\Providers\AddonServiceProvider;
use Statamic\Search\Commands\Update;
use Stillat\DocumentationSearch\Tags\Documentation;
use Wilderborn\Partyline\Facade as Partyline;

class ServiceProvider extends AddonServiceProvider
{
    protected $tags = [
        Documentation::class,
    ];

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/permalinks.php', 'documentation_search.permalinks');
        $this->mergeConfigFrom(__DIR__.'/../config/indexing.php', 'documentation_search.indexing');

        $this->publishes([
            __DIR__.'/../config/permalinks.php' => config_path('documentation_search/permalinks.php'),
            __DIR__.'/../config/indexing.php' => config_path('documentation_search/indexing.php'),
        ], 'documentation-search-config');

        $this->app->bind(
            Contracts\HeadingDetailsExtractor::class,
            function () {
                return new Indexing\HeadingDetailsExtractor(
                    config('documentation_search.permalinks.class_name', 'heading-permalink')
                );
            }
        );

        $this->app->bind(
            Contracts\DocumentSplitter::class,
            Document\Splitter::class,
        );

        $this->app->afterResolving(
            Update::class,
            function ($command) {
                Partyline::bind($command);
            }
        );
    }

    public function bootAddon()
    {
        SearchProvider::register();
    }
}
