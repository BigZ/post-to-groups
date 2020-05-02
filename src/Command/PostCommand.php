<?php

namespace App\Command;

use Facebook\WebDriver\WebDriverKeys;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Panther\Client;

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

    private function postToGroup(Client $client, $group, $message, $image)
    {
        $crawler = $client->request('GET', 'https://www.facebook.com/groups/'.$group);

        // find group name
        $titleColCrawler = $client->waitFor('#leftCol h1 a');
        $title = $titleColCrawler->filter('#leftCol h1 a')->text();

        // open writer
        $composerCrawler = $client->waitFor('#pagelet_group_composer');
        $staleElement = true;
        while($staleElement) {
            try {
                $composer = $composerCrawler->filter('#pagelet_group_composer');
                $staleElement = false;

            } catch(\Exception $element){
                $staleElement = true;
                dump('staled');
            }
        }
        $composer->click();

        // write
        $client->waitFor('#pagelet_group_composer *[contenteditable="true"]');
        $box = $crawler->filter('#pagelet_group_composer *[contenteditable="true"]');

        if ($image) {
            $box->sendKeys($image);
            $box->sendKeys(WebDriverKeys::ENTER);
            sleep(5);
        }

        for ($i = 0; $i <= strlen($image); $i++) {
            $box->sendKeys(WebDriverKeys::BACKSPACE);
        }
        $box->sendKeys(str_replace('%name', $title, $message));
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
        $image = file_get_contents('./image.txt');
        $message = file_get_contents('./message.txt');

        $client = \Symfony\Component\Panther\Client::createChromeClient(
            null,
            ['--disable-notifications']
        );

        $this->login($client, $login, $password);
        $io->note('login done');
        sleep(8);
        foreach ($groups as $group) {
            if ($group) {
                $io->note('posting to group '.$group);
                try {
                    $this->postToGroup($client, $group, $message, $image);
                    sleep(60 * rand(5, 10));
                } catch (\Exception $exception) {

                    $io->error('FAIL.');
                    dump($exception);
                    $client->quit();
                }
            }
        }

        $io->success('DONE.');
        $client->quit();

        return 0;
    }
}
