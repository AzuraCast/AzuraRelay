<?php
use Azura\Event;
use App\Console\Command;

return function (\Azura\EventDispatcher $dispatcher)
{
    // Build CLI commands
    $dispatcher->addListener(Event\BuildConsoleCommands::NAME, function(Event\BuildConsoleCommands $event) {
        $event->getConsole()->addCommands([
            new Command\SetupCommand,
        ]);
    }, 0);
};
