Yii2 Web Socket Private Chat
===============

Online chat based on web sockets and ratchet php. Forked: github.com/joni-jones/yii2-wschat (public chat, with rooms)

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist svbackend/yii2-wschat
```

or add

```
"svbackend/yii2-wschat": "*"
```

to the require section of your `composer.json` file.

Usage
------------

1. The chat extension can use any database storage supported by yii.

    If you would like to use **mysql/mariadb** server keep your current DB configuration and simply import table:
    ```sql
        CREATE TABLE IF NOT EXISTS `history` (
          `id` int(11) NOT NULL,
          `chat_id` varchar(60) COLLATE utf8_unicode_ci DEFAULT NULL,
          `chat_title` varchar(60) COLLATE utf8_unicode_ci DEFAULT NULL,
          `user_id` varchar(60) COLLATE utf8_unicode_ci DEFAULT NULL,
          `username` varchar(60) COLLATE utf8_unicode_ci DEFAULT NULL,
          `avatar_16` varchar(90) COLLATE utf8_unicode_ci DEFAULT NULL,
          `avatar_32` varchar(90) COLLATE utf8_unicode_ci DEFAULT NULL,
          `timestamp` int(11) NOT NULL DEFAULT '0',
          `message` text COLLATE utf8_unicode_ci
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        
        ALTER TABLE `history`
          ADD PRIMARY KEY (`id`);
        
        ALTER TABLE `history`
          MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
    ```

    If `mongodb` extension specified the chat will be try to use it as message history storage, otherwise extension
will be use specified in application config db component.

    The simple example how to use mongodb storage is listed below.
Install [MongoDB](http://docs.mongodb.org/) and [yii2-mongodb](http://www.yiiframework.com/doc-2.0/ext-mongodb-index.html)
extension to store messages history and you need just specify connection in `console` config:

    ```php
        'components' => [
            'mongodb' => [
                'class' => '\yii\mongodb\Connection',
                'dsn' => 'mongodb://username:password@localhost:27017/dbname'
            ]
        ]
    ```
    In created mongodb database you need to create collection named as `history`;


2. To start chat server need to create console command and setup it as demon:
    - Create controller which extends `yii\console\Controller`:
        
        ```php
        ServerController extends \yii\console\Controller
        ```
        
    - Create action to start server:
    
        ```php
        namespace app\commands;

        use svbackend\wschat\components\Chat;
        use svbackend\wschat\components\ChatManager;
        use Ratchet\Server\IoServer;
        use Ratchet\Http\HttpServer;
        use Ratchet\WebSocket\WsServer;
        
        class ServerController extends \yii\console\Controller
        {
            public function actionRun()
            {
                $manager = Yii::configure(new ChatManager(), [
                    'userClassName' => Users::class, // Your User Active Record model class
                ]);
                $server = IoServer::factory(new HttpServer(new WsServer(new Chat($manager))), 8080);

                // If there no connections for a long time - db connection will be closed and new users will get the error
                // so u need to keep connection alive like that
                // Что бы база данных не разрывала соединения изза неактивности
                $server->loop->addPeriodicTimer(60, function () use ($server) {
                    try{
                        Yii::$app->db->createCommand("DO 1")->execute();
                    }catch (Exception $e){
                        Yii::$app->db->close();
                        Yii::$app->db->open();
                    }
                    // Also u can send messages to your cliens right there
                    /*
                    foreach ($server->app->clients as $client) {
                        $client->send("hello client");
                    }*/
                });

                $server->run();
                echo 'Server was started successfully. Setup logging to get more details.'.PHP_EOL;
            }
        }
        ```
       
        
    - Now, you can run chat server with `yii` console command:
    
        ```php
        yii server/run
        ```
        
3. To add chat on page just call:


        
    ```php  
        <?= ChatWidget::widget([
            'auth' => true,
            'user_id' => Yii::$app->user->id // setup id of current logged user
        ]) ?>
    ```
    
        List of available options:
        auth - boolean, default: false
        user_id - mixed, default: null
        port - integer, default: 8080
        chatList - array (allow to set list of preloaded chats), default: [
            id => 1,
            title => 'All'
        ],
        add_room - boolean, default: true (allow to user create new chat rooms)

You can also store added chat, just specify js callback for vent events:

    Chat.vent('chat:add', function(chatModel) {
        console.log(chatModel);
    });
    
This code snipped may be added in your code, but after chat widget loading. In the callback you will get access to ``Chat.Models.ChatRoom`` backbone model. Now, you need add your code to save chat room instead `console.log()`.

> If `YII_DEBUG` is enabled - all js scripts will be loaded separately.

Also by default chat will try to load two images:
`/avatar_16.png` and `/avatar_32.png` from assets folder.

Possible issues
----

If you don't see any messages in console log, check `flushInterval` and `exportInterval` of your log configuration component. The simple configuration may looks like this:
```php
'log' => [
    'traceLevel' => YII_DEBUG ? 3 : 0,
    'flushInterval' => 1,
    'targets' => [
        [
            'class' => 'yii\log\FileTarget',
            'levels' => ['error', 'warning', 'info'],
            'logVars' => [],
            'exportInterval' => 1
        ],
    ],
],
```

License
----

MIT
