<?php

declare(strict_types=1);

namespace Darkwood\IaExceptionBundle\DependencyInjection;

use Darkwood\IaExceptionBundle\Service\ExceptionAiAnalyzer;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

final class DarkwoodIaExceptionExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('darkwood_ia_exception.enabled', $config['enabled']);
        $container->setParameter('darkwood_ia_exception.only_status_codes', $config['only_status_codes']);
        $container->setParameter('darkwood_ia_exception.timeout_ms', $config['timeout_ms']);
        $container->setParameter('darkwood_ia_exception.cache_ttl', $config['cache_ttl']);
        $container->setParameter('darkwood_ia_exception.cache', $config['cache']);
        $container->setParameter('darkwood_ia_exception.include_trace', $config['include_trace']);
        $container->setParameter('darkwood_ia_exception.error_id_generator', $config['error_id_generator']);

        $analyzerDef = $container->getDefinition(ExceptionAiAnalyzer::class);
        $analyzerDef->setArgument('$agent', new Reference($config['agent']));
        $analyzerDef->setArgument('$cache', new Reference($config['cache']));
    }
}
