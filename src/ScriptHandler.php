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
        'backbee-cache-dir'        => 'cache',
        'backbee-log-dir'          => 'log',
        'backbee-data-dir'         => './repository/Data',
        'backbee-data-tmp-dir'     => 'Tmp',
        'backbee-data-media-dir'   => 'Media',
        'backbee-data-Storage-dir' => 'Storage',
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
        if (!is_file(self::bootstrapFilepath()) || is_file(self::doctrineConfigFilepath())) {
            \Incenteev\ParameterHandler\ScriptHandler::buildParameters($event);
            self::$extraParams = Yaml::parse(file_get_contents(self::parametersFilepath()))['parameters'];
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

        self::mkdir(self::buildPath($options['backbee-cache-dir']));
        self::mkdir(self::buildPath($options['backbee-log-dir']));

        $dataDir = $options['backbee-data-dir'];
        self::mkdir(self::buildPath($dataDir));
        self::mkdir(self::buildPath([$dataDir, $options['backbee-data-media-dir']]), 0777);
        self::mkdir(self::buildPath([$dataDir, $options['backbee-data-storage-dir']]), 0777);
        self::mkdir(self::buildPath([$dataDir, $options['backbee-data-tmp-dir']]), 0777);
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

        if (null === self::$extraParams['container_dump_directory']) {
            $containerDumpDir = self::buildPath([self::getOptions($event)['backbee-cache-dir'], 'container']);
            self::mkdir($containerDumpDir);
            self::$extraParams['container_dump_directory'] = $containerDumpDir;
        }

        $bootstrap = [
            'debug'     => self::$extraParams['debug'],
            'container' => [
                'dump_directory' => self::$extraParams['container_dump_directory'],
                'autogenerate'   => self::$extraParams['cache_autogenerate'],
            ]
        ];

        file_put_contents(self::bootstrapFilepath(), Yaml::dump($bootstrap));

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

        $doctrineConfig = [
            'dbal' => [
                'driver'    => $parameters['database_driver'],
                'host'      => $parameters['database_host'],
                'port'      => $parameters['database_port'],
                'dbname'    => $parameters['database_dbname'],
                'user'      => $parameters['database_user'],
                'password'  => $parameters['database_password'],
                'charset'   => $parameters['database_charset'],
                'collation' => $parameters['database_collation'],
            ],
        ];

        file_put_contents(self::doctrineConfigFilepath(), Yaml::dump($doctrineConfig));
    }

    /**
     * Removes parameters.yml if \Incenteev\ParameterHandler\ScriptHandler::buildParameters created it
     */
    public static function clearBackBeeInstall()
    {
        if (null !== self::$extraParams && file_exists(self::parametersFilepath())) {
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
