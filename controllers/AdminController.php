<?php

namespace app\controllers;

use app\models\Users;
use DateTimeImmutable;
use Yii;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\Cors;
use yii\rest\ActiveController;

class AdminController extends ActiveController
{
    public $modelClass = '';
    public $enableCsrfValidation = false; // не работает аудентификация

    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $auth = $behaviors['authenticator'];
        unset($behaviors['authenticator']);

        $behaviors['corsFilter'] = [
            'class' => Cors::class,
            'cors' => [
                'Origin' => [(isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : 'http://' . $_SERVER['REMOTE_ADDR'])],
                'Access-Control-Request-Method' => ['content-type', 'Authorization', 'PATCH'],
                'Access-Control-Request-Headers' => ['Authorization']
            ],
            'actions' => [
                'block-user' => [
                    'Access-Control-Allow-Credentials' => true,
                ]
            ]
                ];

        $auth = [
            'class' => HttpBearerAuth::class,
            'except' => [],
            // 'only' => ['logout'] //или так
        ];

        $behaviors['authenticator'] = $auth;
        return $behaviors;
    }

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['delete'], $actions['create'], $actions['index'], $actions['view'], $actions['update']);
        return $actions;
    }

    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionBlockUser($id = null)
    {
        $identity = Yii::$app->user->identity;
        $user = Users::findOne(['id' => $id]);
        $user->scenario = Users::SCENARIO_BLOCK;
        if ($identity->roles->title == 'Администратор' && !empty($user)) {
            if (isset(Yii::$app->request->post()['block_time'])) {
                $user->block_time = (new DateTimeImmutable(Yii::$app->request->post()['block_time']))->format('Y-m-d H:i:s');
            } else {
                $user->block_time = '2038-01-19 03:14:07';
                $user->deleteUser();
            }
            
            if ($user->validate()) {
                $user->is_block = 1;
                $user->token = null;
                $user->save();
                Yii::$app->response->statusCode = 200;
                $answer = [
                    'data' => [
                        'status' => 'Ok',
                    ]
                ];
            } else {
                Yii::$app->response->statusCode = 422;
                $answer = [
                    'error' => [
                        'code' => 422,
                        'message' => 'Validation error',
                        'errors' => [
                            $user->errors,
                        ],
                    ],
                ];
            }
        } else {
            Yii::$app->response->statusCode = 404;
            $answer = [
                'error' => [
                    'code' => 404,
                    'message' => 'Нет пользователя или нет доступа'
                ]
            ];
        }
        return $this->asJson($answer);
    }

    public function actionUnlock($id = null)
    {
        $identity = Yii::$app->user->identity;
        $user = Users::findOne(['id' => $id]);
        if ($identity->roles->title == 'Администратор' && !empty($user)) {
            $user->is_block = 0;
            $user->block_time = null;
            $user->save(false);
            Yii::$app->response->statusCode = 200;
            $answer = [
                'data' => [
                    'status' => 'Ok',
                ]
            ];
        } else {
            Yii::$app->response->statusCode = 404;
            $answer = [
                'error' => [
                    'code' => 404,
                    'message' => 'Нет пользователя или нет доступа'
                ]
            ];
        }
        return $this->asJson($answer);
    }
}
