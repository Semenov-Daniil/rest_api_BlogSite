<?php

namespace app\controllers;

use app\models\Comments;
use app\models\Posts;
use Yii;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\Cors;
use yii\rest\ActiveController;

class CommentController extends ActiveController
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
                'Access-Control-Request-Method' => ['content-type', 'Authorization', 'DELETE'],
                'Access-Control-Request-Headers' => ['Authorization']
            ],
            'actions' => [
                'logout' => [
                    'Access-Control-Allow-Credentials' => true,
                ]
            ]
                ];

        $auth = [
            'class' => HttpBearerAuth::class,
            'except' => ['get-comments'],
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

    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionGetComments($id_post = null)
    {
        $comments = Comments::find()
            ->where(['Posts_id' => $id_post])
            ->with('comments')
            ->orderBy(['create_at' => SORT_DESC, 'id' => SORT_DESC,])
            ->asArray()
            ->all();

        if (!empty($comments)) {
            foreach ($comments as $keyComment=>$comment) {
                $modelComment = Comments::findOne($comment['id']);
                $comments[$keyComment]['user'] = $modelComment->users;
            }
            Yii::$app->response->statusCode = 200;
            return $this->asJson([
                'data' => [
                    'status' => 'Ok',
                    'comments' => $comments
                ]
            ]);
        } else {
            Yii::$app->response->statusCode = 204;
        }
    }

    public function actionCreateComment($id_post = null)
    {
        $identity = Yii::$app->user->identity;
        $post = Posts::findOne(['id' => $id_post]);

        $answer = [];
        if (!empty($post) && $identity->roles->title == 'Автор' && $identity->id !== $post->Users_id) {
            $comment = new Comments();
            $comment->Posts_id = $post->id;
            $comment->Users_id = $identity->id;
            if ($comment->load(Yii::$app->request->post(), '') && $comment->save()) {
                Yii::$app->response->statusCode == 200;
                $answer = [
                    'data' => [
                        'status' => 'Ok',
                        'comment' => $comment
                    ]
                ];
            } else {
                Yii::$app->response->statusCode = 422;
                $answer = [
                    'error' => [
                        'code' => 422,
                        'message' => 'Validation error',
                        'errors' => [
                            $comment->errors,
                        ],
                    ],
                ];
            }
        } else {
            Yii::$app->response->statusCode = 404;
        }
        return $this->asJson($answer);
    }

    public function actionCreateAnswer($id_post = null, $id_comment = null)
    {
        $identity = Yii::$app->user->identity;
        $post = Posts::findOne(['id' => $id_post]);
        $comment = Comments::findOne(['id' => $id_comment]);

        $responseAnswer = [];
        if (!empty($post) && !empty($comment) && $identity->roles->title == 'Автор' && $identity->id != $comment->Users_id) {
            $answer = new Comments();
            $answer->Posts_id = $post->id;
            $answer->Users_id = $identity->id;
            $answer->parent_id = $comment->id;
            if ($answer->load(Yii::$app->request->post(), '') && $answer->save()) {
                Yii::$app->response->statusCode == 200;
                $responseAnswer = [
                    'data' => [
                        'status' => 'Ok',
                        'answer' => $answer
                    ]
                ];
            } else {
                Yii::$app->response->statusCode = 422;
                $responseAnswer = [
                    'error' => [
                        'code' => 422,
                        'message' => 'Validation error',
                        'errors' => [
                            $answer->errors,
                        ],
                    ],
                ];
            }
        } else {
            Yii::$app->response->statusCode = 404;
        }
        return $this->asJson($responseAnswer);
    }

    public function actionDeleteComment($id_comment = null)
    {
        $identity = Yii::$app->user->identity;
        $comment = Comments::findOne(['id' => $id_comment]);
        $responseAnswer = [];
        if (!empty($comment) && $identity->roles->title == 'Администратор') {
            $comment->deleteAnswer();
            Yii::$app->response->statusCode == 200;
            $responseAnswer = [
                'data' => [
                    'status' => 'Ok'
                ]
            ];
        } else {
            Yii::$app->response->statusCode = 404;
        }
        return $this->asJson($responseAnswer);
    }
}
