<?php

use App\Console\Command;
use Azura\Console\Application;

return function(Application $console)
{
    $console->command(
        'app:nowplaying',
        Command\NowPlayingCommand::class
    )->setDescription('Send "Now Playing" information to the parent AzuraCast server.');

    $console->command(
        'app:setup',
        Command\SetupCommand::class
    )->setDescription('Run initial setup process.');

    $console->command(
        'app:update',
        Command\UpdateCommand::class
    )->setDescription('Update local relay configuration based on remote setup.');
};
