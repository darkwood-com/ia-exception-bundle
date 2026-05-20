<?php

declare(strict_types=1);

namespace Darkwood\IaExceptionBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('darkwood_ia_exception');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->booleanNode('enabled')
            ->defaultFalse()
            ->info('Enable AI exception analysis. Set to true only when appropriate (dev/staging or sanitized production).')
            ->end()
            ->arrayNode('only_status_codes')
            ->integerPrototype()->end()
            ->defaultValue([500])
            ->info('HTTP status codes to augment with AI analysis.')
            ->end()
            ->scalarNode('agent')
            ->defaultValue('ai.agent.default')
            ->info('Symfony AI agent service ID to use for analysis.')
            ->end()
            ->integerNode('timeout_ms')
            ->defaultValue(800)
            ->min(100)
            ->max(5000)
            ->info('Max milliseconds to wait for AI response before fallback.')
            ->end()
            ->integerNode('cache_ttl')
            ->defaultValue(600)
            ->min(0)
            ->info('Cache TTL in seconds (0 = disable cache).')
            ->end()
            ->scalarNode('cache')
            ->defaultValue('cache.app')
            ->info('Cache service ID for AI result caching.')
            ->end()
            ->booleanNode('include_trace')
            ->defaultFalse()
            ->info('Include stack trace in AI input. Only enable in dev environments.')
            ->end()
            ->scalarNode('error_id_generator')
            ->defaultNull()
            ->info('Optional service ID for custom error_id generation. Must return a non-empty string.')
            ->end()
            ->booleanNode('async')
            ->defaultFalse()
            ->info('When true, exception page is returned immediately with a placeholder; AI analysis is loaded asynchronously via /__ai_exception/{error_id}.')
            ->end()
            ->scalarNode('async_route_prefix')
            ->defaultValue('__ai_exception')
            ->info('URL path prefix for the async AI analysis endpoint (e.g. __ai_exception yields /__ai_exception/{error_id}).')
            ->end()
            ->integerNode('async_context_ttl')
            ->defaultValue(300)
            ->min(60)
            ->info('Seconds to keep exception context in cache for async analysis (min 60).')
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
