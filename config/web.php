<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'language' => 'ru-Ru',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'asdf',
            'baseUrl' => '',
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
                'multipart/form-data' => 'yii\web\MultipartFormDataParser',
            ],
        ],
        'response' => [
            // ...
            'formatters' => [
                \yii\web\Response::FORMAT_JSON => [
                    'class' => 'yii\web\JsonResponseFormatter',
                    'prettyPrint' => YII_DEBUG, // use "pretty" output in debug mode
                    'encodeOptions' => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                    // ...
                ],
            ],
            'class' => 'yii\web\Response',
            'on beforeSend' => function ($event) {
                $response = $event->sender;
                // if ($response->statusCode == 401) {
                //     return $response->data = [
                //         'error' => [
                //             'code' => 401,
                //             'message' => "Unauthorized",
                //         ],
                //     ];
                // }
            },
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\Users',
            'enableAutoLogin' => true,
            'enableSession' => false,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,

        'urlManager' => [
            'enablePrettyUrl' => true,
            'enableStrictParsing' => true,
            'showScriptName' => false,
            'rules' => [
                [
                    'pluralize' => true,
                    'prefix' => 'api',
                    'class' => 'yii\rest\UrlRule', 
                    'controller' => 'user',
                    'extraPatterns' => [
                        'POST register' => 'register',
                        'GET avatar' => 'creat-avatar',
                        'POST login' => 'login',

                        'OPTIONS logout' => 'options',
                        'GET logout' => 'logout',

                        'OPTIONS users' => 'options',
                        'GET user/<id>' => 'user-info',

                        'GET users' => 'get-users',
                        'GET posts/<id>' => 'user-posts',

                        'OPTIONS user' => 'options',
                        'GET user' => 'checking-user',
                    ],
                ],
                [
                    'pluralize' => true,
                    'prefix' => 'api',
                    'class' => 'yii\rest\UrlRule', 
                    'controller' => 'post',
                    'extraPatterns' => [
                        'OPTIONS create' => 'options',
                        'POST create' => 'create-post',

                        'OPTIONS update/<id>' => 'options',
                        'PATCH update/<id>' => 'update-post',

                        'GET posts' => 'get-posts',
                        'GET index' => 'get-ten-posts',
                        'GET search' => 'search-posts',
                        'GET post/<id>' => 'get-post',
                        
                        'OPTIONS post/<id>' => 'options',
                        'DELETE post/<id>' => 'delete-post',
                        
                        'OPTIONS view/<id>' => 'options',
                        'PATCH view/<id>' => 'update-views',
                    ],
                ],
                [
                    'pluralize' => true,
                    'prefix' => 'api',
                    'class' => 'yii\rest\UrlRule', 
                    'controller' => 'comment',
                    'extraPatterns' => [
                        'GET comments/<id_post>' => 'get-comments',
                        
                        'OPTIONS create/<id_post>' => 'options',
                        'POST create/<id_post>' => 'create-comment',
                        
                        'OPTIONS answer/<id_post>/<id_comment>' => 'options',
                        'POST answer/<id_post>/<id_comment>' => 'create-answer',
                        
                        'OPTIONS comment/<id_comment>' => 'options',
                        'DELETE comment/<id_comment>' => 'delete-comment'
                    ],
                ],
                [
                    'pluralize' => false,
                    'prefix' => 'api',
                    'class' => 'yii\rest\UrlRule', 
                    'controller' => 'admin',
                    'extraPatterns' => [
                        'OPTIONS block/<id>' => 'options',
                        'PATCH block/<id>' => 'block-user',
                        
                        'OPTIONS unlock/<id>' => 'options',
                        'GET unlock/<id>' => 'unlock',
                    ],
                ],
                [
                    'pluralize' => false,
                    'prefix' => 'api',
                    'class' => 'yii\rest\UrlRule', 
                    'controller' => 'reaction',
                    'extraPatterns' => [
                        'OPTIONS react/<user_id>/<post_id>' => 'options',
                        'GET react/<user_id>/<post_id>' => 'get-reaction-user',
                        
                        'OPTIONS react' => 'options',
                        'POST react' => 'creat-reaction',
                    ],
                ],
            ],
        ]
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        'allowedIPs' => ['*'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        'allowedIPs' => ['*'],
    ];
}

return $config;
