<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PostCommand extends Command
{
    protected static $defaultName = 'app:post';

    protected function configure()
    {
        $this
            ->setDescription('Post to multiple facebook groups')
            ->addArgument('login')
            ->addArgument('password')
        ;
    }

    private function login($client, $login, $password)
    {
        $client->request('GET', 'https://facebook.com/login');
        $crawler = $client->waitFor('#login_form');
        $email = $crawler->filter('#email');
        $email->sendKeys($login);
        sleep(1);
        $pass = $crawler->filter('#pass');
        $pass->sendKeys($password);
        sleep(1);
        $but = $crawler->filter('#loginbutton');
        $but->click();
    }

    private function postToGroup($client, $group, $message)
    {
        $crawler = $client->request('GET', 'https://www.facebook.com/groups/'.$group);

        // open writer
        $composerCrawler = $client->waitFor('#pagelet_group_composer');
        $staleElement = true;
        while($staleElement) {
            try {
                $composer = $composerCrawler->filter('#pagelet_group_composer');
                $staleElement = false;

            } catch(\Exception $element){
                $staleElement = true;
            }
        }
        $composer->click();

        // write
        $client->waitFor('#pagelet_group_composer *[contenteditable="true"]');
        $box = $crawler->filter('#pagelet_group_composer *[contenteditable="true"]');
        $box->sendKeys($message);
        $box->sendKeys("\n");
        sleep(10);

        // submit
        $button = $client->waitFor('#pagelet_group_composer button[type="submit"].selected');
        $actual = $button->filter('#pagelet_group_composer button[type="submit"].selected');
        $actual->click();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $login = $input->getArgument('login');
        $password = $input->getArgument('password');

        $groups = explode("\n", file_get_contents('./groups.txt'));
        $message = file_get_contents('./message.txt');

        try {
            $client = \Symfony\Component\Panther\Client::createChromeClient(
                null,
                ['--disable-notifications']
            );

            $this->login($client, $login, $password);
            $io->note('login done');
            sleep(8);
            foreach ($groups as $group) {
                if (is_numeric($group)) {
                    $io->note('posting to group '.$group);
                    $this->postToGroup($client, $group, $message);
                    $io->note(sprintf('posted to %S, waiting 20s before next one.', $group));
                    sleep(20);
                }
            }

            $io->success('DONE.');
        } catch (\Exception $exception) {
            dump($exception);
            $io->error($exception->getMessage());
            sleep(100);
        }

        return 0;
    }
}
