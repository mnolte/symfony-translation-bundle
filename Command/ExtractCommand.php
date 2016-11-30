<?php

namespace Translation\Bundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\MessageCatalogue;

class ExtractCommand extends ContainerAwareCommand
{
    /**
     * @var ContainerInterface
     */
    private $container;

    protected function configure()
    {
        $this
            ->setName('translation:extract')
            ->setDescription('Extract translations from source code.')
            ->addArgument('configuration', InputArgument::REQUIRED, 'The configuration to use')
            ->addArgument('locale', InputArgument::OPTIONAL, 'The locale ot use. If omitted, we use all configured locales.');

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = $this->getContainer()->get('php_translation.configuration_manager')->getConfiguration($input->getArgument('configuration'));
        $importer = $this->getContainer()->get('php_translation.importer');

        if ($input->hasArgument('locale')) {
            $locales = [$input->getArgument('locale')];
        } else {
            $locales = $this->getContainer()->getParameter('php_translation.locales');
        }

        $transPaths = array_merge($config['external_translations_dirs'], [$config['output_dir']]);
        $catalogues = $this->container->get('php_translation.catalogue_fetcher')->getCatalogues($locales, $transPaths);
        $finder = $this->getConfiguredFinder($config);
        $results = $importer->extractToCatalogues($finder, $catalogues, $config);

        $writer = $this->getContainer()->get('translation.writer');
        foreach ($results as $result) {
            $writer->writeTranslations(
                $result,
                $config['output_format'],
                array(
                    'path' => $config['output_dir'],
                    'default_locale' => $this->getContainer()->getParameter('php_translation.default_locale')
                )
            );
        }
    }

    /**
     * @param array $configuration
     *
     * @return Finder
     */
    private function getConfiguredFinder(array $config)
    {
        // 'dirs', 'excluded_dirs', 'excluded_names'

        $finder = new Finder();
        $finder->in($config['dirs']);

        foreach ($config['excluded_dirs'] as $exclude) {
            $finder->notPath($exclude);
        }

        foreach ($config['excluded_names'] as $exclude) {
            $finder->notName($exclude);
        }

        return $finder;
    }

}