<?php
namespace App\Console\Command;

use App\Entity;
use Azura\Console\Command\CommandAbstract;
use Cake\Chronos\Chronos;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Setup extends CommandAbstract
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

        
    }
}