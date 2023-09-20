<?php

namespace app\controllers;

use app\models\Comments;
use app\models\Posts;
use app\models\Reaction;
use app\models\Users;
use Yii;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\Cors;
use yii\rest\ActiveController;
use yii\web\UploadedFile;

class PostController extends ActiveController
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
                'Access-Control-Request-Method' => ['content-type', 'Authorization', 'PATCH', 'DELETE'],
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
            'except' => ['update-views', 'get-posts', 'search-posts', 'get-ten-posts', 'get-post'],
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

    public function actionCreatePost()
    {
        $post = new Posts();
        // $post->scenario = Posts::SCENARIO_CREATEPOST;

        $identity = Yii::$app->user->identity;

        if ($identity->roles->title == 'Автор') {
            $post->Users_id = $identity->id;
            $post->upload_image_post = UploadedFile::getInstanceByName('upload_image_post');
    
            if ($post->load(Yii::$app->request->post(), '') && $post->validate()) {
                if ($post->upload_image_post) {
                    $post->image = $post->uploadFile();
                }
                $post->content = preg_replace('/\v+|\\\r\\\n/ui','<br>', $post->content);
                $post->save(false);
                Yii::$app->response->statusCode = 200;
                $answer = [
                    'data' => [
                        'status' => 'Ok',
                        'post' => $post,
                    ]
                ];
            } else {
                Yii::$app->response->statusCode = 422;
                $answer = [
                    'error' => [
                        'code' => 422,
                        'message' => 'Validation error',
                        'errors' => [
                            $post->errors,
                        ],
                    ],
                ];
            }
        } else {
            Yii::$app->response->statusCode = 401;
        }
        return $this->asJson($answer);
    }

    public function actionUpdatePost($id = null)
    {
        $identity = Yii::$app->user->identity;

        $post = Posts::findOne($id);
        $post->upload_image_post = UploadedFile::getInstanceByName('upload_image_post');

        if ($post->validateUserID($identity->id)) {
            if ($post->load(Yii::$app->request->post(), '') && $post->validate()) {
                if ($post->upload_image_post) {
                    $post->image = $post->uploadFile();
                }
                $post->content = preg_replace('/\v+|\\\r\\\n/ui','<br>', $post->content);
                $post->save(false);
                Yii::$app->response->statusCode = 200;
                $answer = [
                    'data' => [
                        'status' => 'Ok',
                        'post' => $post,
                    ]
                ];
            } else {
                Yii::$app->response->statusCode = 422;
                $answer = [
                    'error' => [
                        'code' => 422,
                        'message' => 'Validation error',
                        'errors' => [
                            $post->errors,
                        ],
                    ],
                ];
            }
        } else {
            Yii::$app->response->statusCode = 401;
        }
        return $this->asJson($answer);
    }

    public function actionGetTenPosts()
    {
        $posts = Posts::getPosts('desc_create_time', null, 10, 0);
        
        if (!empty($posts)) {
            foreach ($posts as &$post) {
                $modelPost = Posts::findOne($post['id']);
                $post['content'] = preg_replace("/<br>/ui", "\r\n", $post['content']);
                $post['user'] = $modelPost->users;
                $post['create_at'] = Yii::$app->formatter->asDate($modelPost->create_at, 'd.m.Y H:i');
                $post['update_at'] = Yii::$app->formatter->asDate($modelPost->update_at, 'd.m.Y H:i');
            }

            Yii::$app->response->statusCode = 200;
            return $this->asJson([
                'data' => [
                    'status' => 'Ok',
                    'posts' => $posts,
                ]
            ]);
        } else {
            Yii::$app->response->statusCode = 204;
        }
    }

    public function actionGetPosts()
    {
        $page = Yii::$app->request->get('page') ? Yii::$app->request->get('page') : 1;
        $options = Yii::$app->request->get('options');
        $flag = Yii::$app->request->get('flag');
        $countPostsOnePage = 10;

        $posts = Posts::getPosts($flag, $options, 10, ($page*$countPostsOnePage) - $countPostsOnePage);

        if (!empty($posts)) {
            foreach ($posts as &$post) {
                $modelPost = Posts::findOne($post['id']);
                $post['content'] = preg_replace("/<br>/ui", "\r\n", $post['content']);
                $post['user'] = $modelPost->users;
                $post['create_at'] = Yii::$app->formatter->asDate($modelPost->create_at, 'd.m.Y H:i');
                $post['update_at'] = Yii::$app->formatter->asDate($modelPost->update_at, 'd.m.Y H:i');
            }

            $paginaction = Posts::getPaginaction(count(Posts::getPosts($flag, $options)), $countPostsOnePage, $page);

            Yii::$app->response->statusCode = 200;
            return $this->asJson([
                'data' => [
                    'status' => 'Ok',
                    'posts' => $posts,
                    'paginaction' => $paginaction,
                ]
            ]);
        } else {
            Yii::$app->response->statusCode = 204;
        }
    }

    public function actionSearchPosts()
    {
        $options = Yii::$app->request->get('options');
        $flag = Yii::$app->request->get('flag');

        $posts = Posts::getPosts($flag, $options);

        if (!empty($posts)) {
            foreach ($posts as &$post) {
                $modelPost = Posts::findOne($post['id']);
                $post['content'] = preg_replace("/<br>/ui", "\r\n", $post['content']);
                $post['user'] = $modelPost->users;
                $post['create_at'] = Yii::$app->formatter->asDate($modelPost->create_at, 'd.m.Y H:i');
                $post['update_at'] = Yii::$app->formatter->asDate($modelPost->update_at, 'd.m.Y H:i');
            }
            unset($post);
            Yii::$app->response->statusCode = 200;
            return $this->asJson([
                'data' => [
                    'status' => 'Ok',
                    'posts' => $posts,
                ]
            ]);
        } else {
            Yii::$app->response->statusCode = 204;
        }
    }

    public function actionGetPost($id = null)
    {
        $post = Posts::find()
            ->where(['id' => $id])
            ->asArray()
            ->one();

        if (!empty($post)) {
            $modelPost = Posts::findOne($post['id']);
            $post['content'] = preg_replace("/<br>/ui", "\r\n", $post['content']);
            $post['comments'] = $modelPost->comments;
            $post['countComments'] = count($modelPost->comments);
            $post['countLike'] = count(array_filter($modelPost->reactions, function($react) { return $react->reaction == 1; }));
            $post['countDislike'] = count(array_filter($modelPost->reactions, function($react) { return $react->reaction == 0; }));
            $post['user'] = $modelPost->users;
            $post['create_at'] = Yii::$app->formatter->asDate($modelPost->create_at, 'd.m.Y H:i');
            $post['update_at'] = Yii::$app->formatter->asDate($modelPost->update_at, 'd.m.Y H:i');

            Yii::$app->response->statusCode = 200;
            return $this->asJson([
                'data' => [
                    'status' => 'Ok',
                    'post' => $post,
                ]
            ]);
        } else {
            Yii::$app->response->statusCode = 204;
        }
    }

    public function actionDeletePost($id = null)
    {
        $post = Posts::findOne(['id' => $id]);
        $identity = Yii::$app->user->identity;

        if (!empty($post)) {
            if (($identity->roles->title == 'Автор' && $post->validateUserID($identity->id) && count($post->comments) == 0) || $identity->roles->title == 'Администратор') {

                $post->deletePost();
                Yii::$app->response->statusCode = 200;
                $answer = [
                    'data' => [
                        'status' => 'Ok',
                    ],
                ];
            } else {
                Yii::$app->response->statusCode = 403;
                $answer = [
                    'error' => [
                        'code' => 403,
                        'message' => 'Access delete'
                    ],
                ];
            }
        } else {
            Yii::$app->response->statusCode = 204;
            $answer = [
                'data' => [
                    'status' => 'Not content',
                ],
            ];
        }
        return $this->asJson($answer);
    }

    public function actionUpdateViews($id = null)
    {
        $post = Posts::findOne($id);

        $post->views = $post->views + 1;
        $post->save();

        Yii::$app->response->statusCode = 200;
        $answer = [
            'data' => [
                'status' => 'Ok',
                'post' => $post,
            ]
        ];
        return $this->asJson($answer);
    }
}
