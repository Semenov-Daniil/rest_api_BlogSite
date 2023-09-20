<?php

namespace app\controllers;

use app\models\Posts;
use app\models\Reaction;
use Yii;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\Cors;

class ReactionController extends \yii\rest\ActiveController
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
                // 'logout' => [
                //     'Access-Control-Allow-Credentials' => true,
                // ]
            ]
                ];

        $auth = [
            'class' => HttpBearerAuth::class,
            'except' => [''],
            // 'only' => ['logout'] //или так
        ];

        $behaviors['authenticator'] = $auth;
        return $behaviors;
    }

    public function actions()
    {
        $actions = parent::actions();

        // disable the "delete" and "create" actions
        unset($actions['delete'], $actions['create'], $actions['index'], $actions['view'], $actions['update']);

        return $actions;
    }

    public function actionGetReactionUser($user_id = null, $post_id = null)
    {
        $reaction = Reaction::find()
            ->where(['Users_id' => $user_id])
            ->andWhere(['Posts_id' => $post_id])
            ->asArray()
            ->all();

        if (!empty($reaction)) {
            Yii::$app->response->statusCode = 200;
            return $this->asJson([
                'data' => [
                    'status' => 'Ok',
                    'reaction' => $reaction,
                ]
            ]);
        } else {
            Yii::$app->response->statusCode = 204;
        }
    }

    public function actionCreatReaction()
    {
        $post = Yii::$app->request->post();
        $react = Reaction::findOne(['Users_id' => $post['Users_id']]);

        if (!empty($react)) {
            if ($react->reaction == $post['reaction']) {
                $react->delete();
                Yii::$app->response->statusCode = 200;
                return $this->asJson([
                    'data' => [
                        'status' => 'Ok'
                    ]
                ]);
            } else {
                $react->delete();
                $react = new Reaction();
                $react->load($post, '');
                if ($react->save()) {
                    Yii::$app->response->statusCode = 200;
                    return $this->asJson([
                        'data' => [
                            'status' => 'Ok',
                            'reaction' => $react,
                        ]
                    ]);
                } else {
                    Yii::$app->response->statusCode = 404;
                }
            }
        } else {
            $react = new Reaction();
            $react->load($post, '');
            if ($react->save()) {
                Yii::$app->response->statusCode = 200;
                return $this->asJson([
                    'data' => [
                        'status' => 'Ok',
                        'reaction' => $react,
                    ]
                ]);
            } else {
                Yii::$app->response->statusCode = 404;
            }
        }

        
    }

}
