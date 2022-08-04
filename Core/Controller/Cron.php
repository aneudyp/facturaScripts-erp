<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2017-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Core\Controller;

use DateTime;
use FacturaScripts\Core\Base\PluginManager;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Contract\ControllerInterface;

class Cron implements ControllerInterface
{

    public function __construct(string $url)
    {
    }

    public function run(): void
    {
        header('Content-Type: text/plain');
        echo <<<END
  ______         _                    _____           _       _       
 |  ____|       | |                  / ____|         (_)     | |      
 | |__ __ _  ___| |_ _   _ _ __ __ _| (___   ___ _ __ _ _ __ | |_ ___ 
 |  __/ _` |/ __| __| | | | '__/ _` |\___ \ / __| '__| | '_ \| __/ __|
 | | | (_| | (__| |_| |_| | | | (_| |____) | (__| |  | | |_) | |_\__ \
 |_|  \__,_|\___|\__|\__,_|_|  \__,_|_____/ \___|_|  |_| .__/ \__|___/
                                                       | |            
                                                       |_|                                   

END;

        $startTime = new DateTime();
        $this->echo('starting-cron');

        // ejecutamos el cron de cada plugin
        $manager = new PluginManager();
        foreach ($manager->enabledPlugins() as $plugin) {
            $cronClass = '\\FacturaScripts\\Plugins\\' . $plugin . '\\Cron';
            if (false === class_exists($cronClass)) {
                continue;
            }

            $this->echo('running-plugin-cron', ['%pluginName%' => $plugin]);
            $cron = new $cronClass($plugin);
            $cron->run();
        }

        $endTime = new DateTime();
        $executionTime = $startTime->diff($endTime);
        $this->echo('finished-cron', ['%timeNeeded%' => $executionTime->format("%H:%I:%S")]);
    }

    private function echo(string $message, array $context = []): void
    {
        $text = ToolBox::i18n()->trans($message, $context);

        echo $text . PHP_EOL;
        ToolBox::log('cron')->notice($text);
    }
}
