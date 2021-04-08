<?php

use Imi\App;
use Imi\Event\EventParam;
use Swoole\Runtime;
use Yurun\Swoole\CoPool\CoPool;
use Yurun\Swoole\CoPool\Interfaces\ICoTask;
use Yurun\Swoole\CoPool\Interfaces\ITaskParam;

/**
 * @return bool
 */
function checkHttpServerStatus()
{
    $serverStarted = false;
    for ($i = 0; $i < 60; ++$i)
    {
        sleep(1);
        $context = stream_context_create(['http' => ['timeout' => 1]]);
        if ('' === @file_get_contents('http://127.0.0.1:8080/', false, $context))
        {
            $serverStarted = true;
            break;
        }
    }

    return $serverStarted;
}

/**
 * 开启服务器.
 *
 * @return void
 */
function startServer()
{
    $dirname = dirname(__DIR__);
    $servers = [
        'HttpServer'    => [
            'start'         => $dirname . '/example/bin/start-server.sh',
            'stop'          => $dirname . '/example/bin/stop-server.sh',
            'checkStatus'   => 'checkHttpServerStatus',
        ],
    ];

    $pool = new CoPool(swoole_cpu_num(), 16,
        // 定义任务匿名类，当然你也可以定义成普通类，传入完整类名
        // @phpstan-ignore-next-line
        new class() implements ICoTask {
            /**
             * 执行任务
             *
             * @param ITaskParam $param
             *
             * @return mixed
             */
            public function run(ITaskParam $param)
            {
                ($param->getData())();
                // 执行任务
                return true; // 返回任务执行结果，非必须
            }
        }
    );
    $pool->run();

    $taskCount = count($servers);
    $completeTaskCount = 0;
    foreach ($servers as $name => $options)
    {
        // 增加任务，异步回调
        $pool->addTaskAsync(function () use ($options, $name) {
            // start server
            $cmd = $options['start'];
            echo "Starting {$name}...", \PHP_EOL;
            `{$cmd}`;

            register_shutdown_function(function () use ($name, $options) {
                // stop server
                $cmd = $options['stop'];
                echo "Stoping {$name}...", \PHP_EOL;
                `{$cmd}`;
                echo "{$name} stoped!", \PHP_EOL, \PHP_EOL;
            });

            if (($options['checkStatus'])())
            {
                echo "{$name} started!", \PHP_EOL;
            }
            else
            {
                throw new \RuntimeException("{$name} start failed");
            }
        }, function (ITaskParam $param, $data) use (&$completeTaskCount) {
            // 异步回调
            ++$completeTaskCount;
        });
    }

    while ($completeTaskCount < $taskCount)
    {
        usleep(10000);
    }
    $pool->stop();
}

/**
 * @return void
 */
function test()
{
    $descriptorspec = [
        ['pipe', 'r'],  // 标准输入，子进程从此管道中读取数据
        ['pipe', 'w'],  // 标准输出，子进程向此管道中写入数据
    ];
    $cmd = __DIR__ . '/phpunit -c ' . __DIR__ . '/phpunit.xml';
    $pipes = [];
    $processHndler = proc_open($cmd, $descriptorspec, $pipes);
    $records2 = [];
    while (!feof($pipes[1]))
    {
        $content = fgets($pipes[1]);
        if (false !== $content)
        {
            if (2 === count($records2))
            {
                array_shift($records2);
            }
            $records2[] = $content;
            echo $content;
        }
    }

    do
    {
        $status = proc_get_status($processHndler);
    } while ($status['running'] ?? false);
    foreach ($pipes as $pipe)
    {
        fclose($pipe);
    }
    proc_close($processHndler);

    // @phpstan-ignore-next-line
    if (version_compare(\SWOOLE_VERSION, '4.4', '<') && 255 === ($status['exitcode'] ?? 0) && 'OK' === substr($records2[0] ?? '', 0, 2))
    {
        exit(0);
    }
    else
    {
        exit($status['exitcode'] ?? 0);
    }
}

(function () {
    $redis = new \Redis();
    if (!$redis->connect('127.0.0.1', 6379))
    {
        exit('Redis connect failed');
    }
    $redis->del($redis->keys('imi-amqp:*'));
    $redis->close();
})();

register_shutdown_function(function () {
    echo 'Shutdown memory:', \PHP_EOL, `free -m`, \PHP_EOL;
});

echo 'Before start server memory:', \PHP_EOL, `free -m`, \PHP_EOL;
startServer();
echo 'After start server memory:', \PHP_EOL, `free -m`, \PHP_EOL;

App::initFramework('ImiApp');

\Imi\Event\Event::on('IMI.INIT_TOOL', function (EventParam $param) {
    $data = $param->getData();
    $data['skip'] = true;
    \Imi\Tool\Tool::init();
});
\Imi\Event\Event::on('IMI.INITED', function (EventParam $param) {
    App::initWorker();
    Runtime::enableCoroutine();
    $param->stopPropagation();
}, 1);
App::run('ImiApp');
