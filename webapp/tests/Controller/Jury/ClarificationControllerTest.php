<?php declare(strict_types=1);

namespace App\Tests\Controller\Jury;

use App\Controller\Jury\ClarificationController;
use App\Tests\BaseTest;

class ClarificationControllerTest extends BaseTest
{
    protected static $roles = ['jury'];

    /**
     * Test that the jury clarifications page contains the correct information
     */
    public function testClarificationRequestIndex()
    {
        $this->logIn();
        $crawler = $this->client->request('GET', '/jury');

        $response = $this->client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals(200, $response->getStatusCode(), $message);

        $link = $crawler->selectLink('Clarifications')->link();
        $message = var_export($link, true);
        $this->assertEquals('http://localhost/jury/clarifications', $link->getUri(), $message);

        $crawler = $this->client->click($link);

        $h3s = $crawler->filter('h3')->extract(array('_text'));
        $this->assertEquals('New requests:', $h3s[0]);
        $this->assertEquals('Old requests:', $h3s[1]);
        $this->assertEquals('General clarifications:', $h3s[2]);

        $this->assertEquals(1, $crawler->filter('html:contains("Can you tell me how")')->count());
        $this->assertEquals(1, $crawler->filter('html:contains("21:47")')->count());
    }

    /**
     * Test that the jury can view a clarification
     */
    public function testClarificationRequestView()
    {
        $this->logIn();
        $crawler = $this->client->request('GET', '/jury/clarifications/1');

        $response = $this->client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals(200, $response->getStatusCode(), $message);

        $pres = $crawler->filter('pre')->extract(array('_text'));
        $this->assertEquals('Can you tell me how to solve this problem?', $pres[0]);
        $this->assertEquals("> Can you tell me how to solve this problem?\r\n\r\nNo, read the problem statement.", $pres[1]);

        $link = $crawler->selectLink('Example teamname (t2)')->link();
        $message = var_export($link, true);
        $this->assertEquals('http://localhost/jury/teams/2', $link->getUri(), $message);
    }

    /**
     * Test that the jury can send a clarification
     */
    public function testClarificationRequestComposeForm()
    {
        $this->logIn();
        $crawler = $this->client->request('GET', '/jury/clarifications');

        $link = $crawler->selectLink('Send clarification')->link();
        $message = var_export($link, true);
        $this->assertEquals('http://localhost/jury/clarifications/send', $link->getUri(), $message);

        $crawler = $this->client->click($link);

        $h1s = $crawler->filter('h1')->extract(array('_text'));
        $this->assertEquals('Send Clarification', $h1s[0]);

        $options = $crawler->filter('option')->extract(array('_text'));
        $this->assertEquals('ALL', $options[1]);
        $this->assertEquals('DOMjudge (t1)', $options[2]);
        $this->assertEquals('Example teamname (t2)', $options[3]);

        $labels = $crawler->filter('label')->extract(array('_text'));
        $this->assertEquals('Send to:', $labels[0]);
        $this->assertEquals('Subject:', $labels[1]);
        $this->assertEquals('Message:', $labels[2]);
    }
}