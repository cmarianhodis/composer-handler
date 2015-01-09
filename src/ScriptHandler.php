<?php

/*
 * Copyright (c) 2011-2015 Lp digital system
 *
 * This file is part of BackBee Standard Edition.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * BackBee Standard Edition is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee Standard Edition. If not, see <http://www.gnu.org/licenses/>.
 */

namespace BackBee\Standard\Composer;

use Composer\Script\CommandEvent;
use Symfony\Component\Yaml\Yaml;

/**
 * @author e.chau <eric.chau@lp-digital.fr>
 */
class ScriptHandler
{
    /**
     * project root directory
     * @var string
     */
    private static $rootDir;

    /**
     * composer extra parameters default value
     * @var array
     */
    private static $options = [
        'prompt'                   => true,
        'generate-structure'       => true,
        'backbee-cache-dir'        => 'cache',
        'backbee-log-dir'          => 'log',
        'backbee-data-dir'         => './repository/Data',
    ];

    /**
     * composer extra parameters
     * @var array|null
     */
    private static $extraParams;

    /**
     * Calls to \Incenteev\ParameterHandler\ScriptHandler if bootstrap.yml and/or doctrine.yml are not
     * created
     *
     * @param  CommandEvent $event
     */
    public static function buildParameters(CommandEvent $event)
    {
        if (
            true === self::getOptions($event)['prompt']
            && (
                !is_file(self::bootstrapFilepath())
                || !is_file(self::doctrineConfigFilepath())
                || !is_file(self::servicesFilepath())
            )
        ) {
            \Incenteev\ParameterHandler\ScriptHandler::buildParameters($event);
            self::$extraParams = self::readYamlFile(self::parametersFilepath())['parameters'];
        }
    }

    /**
     * Generates BackBee folder basic structure (cache/, log/, Data/ directories)
     *
     * @param  CommandEvent $event
     */
    public static function buildBackBeeStructure(CommandEvent $event)
    {
        $options = self::getOptions($event);
        if (false === $options['generate-structure']) {
            return;
        }

        $umask = umask();
        umask(0);
        self::mkdir($cacheDir = self::buildPath($options['backbee-cache-dir']), 0777);
        self::mkdir($logDir = self::buildPath($options['backbee-log-dir']), 0777);

        $dataDirname = $options['backbee-data-dir'];
        self::mkdir($dataDir = self::buildPath($dataDirname));
        self::mkdir(self::buildPath([$dataDirname, 'Media']), 0777);
        self::mkdir(self::buildPath([$dataDirname, 'Storage']), 0777);
        self::mkdir(self::buildPath([$dataDirname, 'Tmp']), 0777);
        umask($umask);

        $servicesConfig = self::readYamlFile(self::servicesFilepath()) ?: [];

        if (!array_key_exists('parameters', $servicesConfig)) {
            $servicesConfig['parameters'] = [];
        }

        if (!isset($servicesConfig['parameters']['bbapp.cache.dir'])) {
            $servicesConfig['parameters']['bbapp.cache.dir'] = realpath($cacheDir);
        }

        if (!isset($servicesConfig['parameters']['bbapp.log.dir'])) {
            $servicesConfig['parameters']['bbapp.log.dir'] = realpath($logDir);
        }

        if (!isset($servicesConfig['parameters']['bbapp.data.dir'])) {
            $servicesConfig['parameters']['bbapp.data.dir'] = realpath($dataDir);
        }

        self::writeYamlFile(self::servicesFilepath(), $servicesConfig);
    }

    /**
     * If bootstrap.yml does not exist in repository/Config, this method will create it
     *
     * @param  CommandEvent $event
     */
    public static function buildBootstrap(CommandEvent $event)
    {
        if (is_file(self::bootstrapFilepath()) || null === self::$extraParams) {
            return;
        }

        $options = self::getOptions($event);
        if (null === self::$extraParams['container_dump_directory'] && true === $options['generate-structure']) {
            $containerDumpDir = self::buildPath([$options['backbee-cache-dir'], 'container']);
            self::mkdir($containerDumpDir);
            self::$extraParams['container_dump_directory'] = $containerDumpDir;
        }

        self::writeYamlFile(self::bootstrapFilepath(), [
            'debug'     => self::$extraParams['debug'],
            'container' => [
                'dump_directory' => self::$extraParams['container_dump_directory'],
                'autogenerate'   => self::$extraParams['cache_autogenerate'],
            ]
        ]);

    }

    /**
     * Builds doctrine configuration files into repository/Config folder if it does not exist
     *
     * @param  CommandEvent $event
     */
    public static function buildDoctrineConfig(CommandEvent $event)
    {
        if (is_file(self::doctrineConfigFilepath()) || null === self::$extraParams) {
            return;
        }

        self::writeYamlFile(self::doctrineConfigFilepath(), [
            'dbal' => [
                'driver'    => self::$extraParams['database_driver'],
                'host'      => self::$extraParams['database_host'],
                'port'      => self::$extraParams['database_port'],
                'dbname'    => self::$extraParams['database_dbname'],
                'user'      => self::$extraParams['database_user'],
                'password'  => self::$extraParams['database_password'],
                'charset'   => self::$extraParams['database_charset'],
                'collation' => self::$extraParams['database_collation'],
            ],
        ]);
    }

    /**
     * Builds services configuration files into repository/Config folder if it does not exist
     *
     * @param  CommandEvent $event
     */
    public static function buildServicesConfig(CommandEvent $event)
    {
        if (null === self::$extraParams) {
            return;
        }

        $services = self::readYamlFile(self::servicesFilepath()) ?: [];
        if (!array_key_exists('parameters', $services)) {
            $services['parameters'] = [];
        }

        if (!isset($services['parameters']['secret_key'])) {
            $services['parameters']['secret_key'] = self::$extraParams['secret_key'];
        }

        self::writeYamlFile(self::servicesFilepath(), $services);
    }

    /**
     * Removes parameters.yml if \Incenteev\ParameterHandler\ScriptHandler::buildParameters created it
     */
    public static function clearBackBeeInstall()
    {
        if (file_exists(self::parametersFilepath())) {
            unlink(self::parametersFilepath());
        }
    }

    /**
     * Builds path with project root directory as path base
     *
     * @param  string|array $path
     * @return string
     */
    protected static function buildPath($path)
    {
        $path = implode('/', array_merge([self::rootDir()], (array) $path));

        return preg_replace('#//+#', '/', $path);
    }

    /**
     * Project root directory getter
     *
     * @return string
     */
    protected static function rootDir()
    {
        if (null === self::$rootDir) {
            self::$rootDir = getcwd();
        }

        return self::$rootDir;
    }

    /**
     * Returns project repository config directory path
     *
     * @return string
     */
    protected static function repositoryConfigDir()
    {
        return self::buildPath(['repository', 'Config']);
    }

    /**
     * Returns bootstrap.yml filepath
     *
     * @return string
     */
    protected static function bootstrapFilepath()
    {
        return self::repositoryConfigDir().'/bootstrap.yml';
    }

    /**
     * Returns doctrine.yml filepath
     *
     * @return string
     */
    protected static function doctrineConfigFilepath()
    {
        return self::repositoryConfigDir().'/doctrine.yml';
    }

    /**
     * Returns services.yml filepath
     *
     * @return string
     */
    protected static function servicesFilepath()
    {
        return self::repositoryConfigDir().'/services.yml';
    }

    /**
     * Returns parameters.yml (generated by \Incenteev\ParameterHandler\ScriptHandler::buildParameters)
     * filepath
     *
     * @return string
     */
    protected static function parametersFilepath()
    {
        return self::repositoryConfigDir().'/parameters.yml';
    }

    /**
     * Reads Yaml file if provided path exists
     *
     * @param  string $path
     * @return null|array
     */
    protected static function readYamlFile($path)
    {
        $result = null;
        if (is_readable($path)) {
            $result = Yaml::parse(file_get_contents($path));
        }

        return $result;
    }

    /**
     * @param  string $path
     * @param  array $config
     */
    protected static function writeYamlFile($path, array $config)
    {
        file_put_contents($path, Yaml::dump($config));
    }

    /**
     * Alias to Php mkdir() function but it occurs only if provided directory path does not exist
     *
     * @param  string  $dirname
     * @param  integer $mode
     */
    protected static function mkdir($dirname, $mode = 0755)
    {
        if (!is_dir($dirname)) {
            mkdir($dirname, $mode);
        }
    }

    /**
     * Retrieves composer.json 'extra' parameters and merge it with default option values
     *
     * @param  CommandEvent $event
     * @return array
     */
    protected static function getOptions(CommandEvent $event)
    {
        return array_merge(self::$options, $event->getComposer()->getPackage()->getExtra());
    }
}
