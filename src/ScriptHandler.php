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
     * Composer extra parameters default value
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
     * @param  CommandEvent $event [description]
     */
    public static function buildBootstrap(CommandEvent $event)
    {
        $configDir = self::buildPath(['repository', 'Config']);
        if (is_file($configDir.'/bootstrap.yml')) {
            return;
        }

        $parameters = Yaml::parse(file_get_contents($configDir.'/parameters.yml'))['parameters'];

        if (null === $parameters['container_dump_directory']) {
            $containerDumpDir = self::buildPath([self::getOptions($event)['backbee-cache-dir'], 'container']);
            self::mkdir($containerDumpDir);
            $parameters['container_dump_directory'] = $containerDumpDir;
        }

        $bootstrap = [
            'debug'     => $parameters['debug'],
            'container' => [
                'dump_directory' => $parameters['container_dump_directory'],
                'autogenerate'   => $parameters['cache_autogenerate'],
            ]
        ];

        file_put_contents($configDir.'/bootstrap.yml', Yaml::dump($bootstrap));

    }

    /**
     * Builds doctrine configuration files into repository/Config folder if it does not exist
     *
     * @param  CommandEvent $event
     */
    public static function buildDoctrineConfig(CommandEvent $event)
    {
        $configDir = self::buildPath(['repository', 'Config']);
        if (is_file($configDir.'/doctrine.yml')) {
            return;
        }

        $parameters = Yaml::parse(file_get_contents($configDir.'/parameters.yml'))['parameters'];

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

        file_put_contents($configDir.'/doctrine.yml', Yaml::dump($doctrineConfig));
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
