<?php
namespace Imi\Grpc\Test;

use Yurun\Util\HttpRequest;

class RabbitMQTest extends BaseTest
{
    private $host = 'http://127.0.0.1:8080/';

    public function testPublish()
    {
        $http = new HttpRequest;
        $response = $http->get($this->host . 'publish?memberId=20180621');
        $this->assertEquals([
            'r1'    =>  true,
            'r2'    =>  true,
        ], $response->json(true));
    }

    public function testConsum()
    {
        $http = new HttpRequest;
        $excepted = [
            'r1'    =>  '{"memberId":20180621}',
            'r2'    =>  '{"memberId":20180621,"content":"memberId:20180621"}',
        ];
        for($i = 0; $i < 3; ++$i)
        {
            $response = $http->get($this->host . 'consume?memberId=20180621');
            $data = $response->json(true);
            if($excepted === $data)
            {
                break;
            }
            sleep(1);
        }
        $this->assertEquals($excepted, $data);
    }

}
