<?php
namespace App\Console\Command;

use App\Service\AzuraCast;
use Azura\Console\Command\CommandAbstract;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SetupCommand extends CommandAbstract
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('app:setup')
            ->setDescription('Run initial setup process.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('AzuraRelay Setup');
        $io->section('Welcome to AzuraRelay! Provide the following items to finish setup.');

        $io->section('AzuraRelay has to connect to a "parent" AzuraCast instance to relay its broadcast(s). Provide the base URL of that installation (including "http://" or "https://") to continue.');

        $question = new Question\Question('AzuraCast Installation URL');
        $io->ask($question, $_ENV[AzuraCast::ENV_BASE_URL], function($value) {



        });

        
    }
}
