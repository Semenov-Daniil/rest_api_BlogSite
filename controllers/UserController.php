<?php

namespace app\controllers;

use app\models\Comments;
use app\models\Posts;
use app\models\Reaction;
use app\models\Users;
use DateTime;
use Yii;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\Cors;
use yii\rest\ActiveController;
use yii\web\UploadedFile;

class UserController extends ActiveController
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
                'Access-Control-Request-Method' => ['content-type', 'Authorization'],
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
            'except' => ['register', 'login', 'user-info', 'user-posts', 'creat-avatar'],
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

    public function actionRegister()
    {
        $user = new Users();
        $user->scenario = Users::SCENARIO_REGISTER;

        $user->upload_avatar = UploadedFile::getInstanceByName('upload_avatar');
        if ($user->load(Yii::$app->request->post(), '') && $user->validate()) {

            if ($user->upload_avatar) {
                $user->avatar = $user->uploadFile();
            }

            $newToken = Yii::$app->security->generateRandomString();
            $user->token = $newToken;
            $user->password = Yii::$app->getSecurity()->generatePasswordHash($user->password);
            $user->save(false);

            Yii::$app->response->statusCode = 200;
            $answer = [
                'data' => [
                    'status' => 'Ok',
                    'user' => $user,
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
        return $this->asJson($answer);
    }

    public function actionCreatAvatar()
    {
        $user = new Users();
        $user->scenario = Users::SCENARIO_CREAT_AVATAR;

        $login = Yii::$app->request->get('login');
        $titleAvatar = Yii::$app->request->get('src_avatar');

        if ($user->load(['login' => $login], '') && $user->validate()) {

            if ($src_avatar = $user->creatAvatar($titleAvatar)) {
                Yii::$app->response->statusCode = 200;
                $answer = [
                    'data' => [
                        'status' => 'Ok',
                        'src_avatar' => $src_avatar
                    ]
                ];
            } else {
                Yii::$app->response->statusCode = 404;
                $answer = [
                    'error' => [
                        'code' => 404,
                        'message' => 'Not creat avatar'
                    ],
                ];
            }

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
        return $this->asJson($answer);
    }

    public function actionLogin()
    {
        $user = new Users();
        // $user->scenario = Users::SCENARIO_LOGIN;

        if ($user->load(Yii::$app->request->post(), '') && $user->validate()) {

            if ($userData = Users::findOne(['login' => $user->login])) {
                if ($user->validatePassword($userData->password)) {
                    $user = Users::findOne(['login' => $user->login]);
                    $user->isBlock();
                    if (!$user->is_block) {
                        $newToken = Yii::$app->security->generateRandomString();
                        $user->token = $newToken;
                        $user->save(false);

                        Yii::$app->response->statusCode = 200;
                        $answer = [
                            'data' => [
                                'status' => 'Ok',
                                'user' => $user,
                                'role' => $user->roles->title,
                            ]
                        ];
                    } else {
                        Yii::$app->response->statusCode = 401;
                        $answer = [
                            'error' => [
                                'code' => 401,
                                'errors' => [
                                    ['block' => ['Вы заблокированы']],
                                ],
                            ],
                        ];
                    }

                    
                } else {
                    Yii::$app->response->statusCode = 422;
                    $answer = [
                        'error' => [
                            'code' => 422,
                            'message' => 'Validation error',
                            'errors' => [
                                ['password' => ['Password incorrect']],
                            ],
                        ],
                    ];
                }
            } else {
                Yii::$app->response->statusCode = 422;
                    $answer = [
                        'error' => [
                            'code' => 422,
                            'message' => 'Validation error',
                            'errors' => [
                                ['password' => ['Login incorrect']],
                            ],
                        ],
                    ];
            }
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
        return $this->asJson($answer);
    }

    public function actionLogout()
    {
        if ($user = Users::findOne(['id' => Yii::$app->user->id])) {
            $user->token = null;
            $user->save(false);
            Yii::$app->response->statusCode = 200;
        }
    }

    public function actionUserInfo($id = null)
    {
        $userData = Users::findOne($id);
        $countPostsUser = Posts::find()->where(['Users_id' => $id])->count();
        Yii::$app->response->statusCode = 200;
        return $this->asJson([
            'data' => [
                'status' => 'Ok',
                'user' => $userData,
                'count_posts_user' => $countPostsUser,
            ],
        ]);
    }

    public function actionGetUsers()
    {
        $flag = Yii::$app->request->get('flag');
        $options = Yii::$app->request->get('options');

        $identity = Yii::$app->user->identity;

        if ($identity->roles->title == 'Администратор') {
            $users = USers::getUsers($flag, $options);

            Yii::$app->response->statusCode = 200;
            return $this->asJson([
                'data' => [
                    'status' => 'Ok',
                    'users' => $users,
                ],
            ]);
        }
        Yii::$app->response->statusCode = 401;
    }

    public function actionUserPosts($id = null)
    {
        $userPosts = Posts::getPosts('desc_create_time', null, null, null, ['Users_id' => $id]);
        
        foreach ($userPosts as &$post) {
            $post['user'] = Users::findOne($id);
        }

        Yii::$app->response->statusCode = 200;
        return $this->asJson([
            'data' => [
                'status' => 'Ok',
                'posts' => $userPosts,
            ],
        ]);
    }

    public function actionCheckingUser()
    {
        $identity = Yii::$app->user->identity;
        $user = Users::findOne(Yii::$app->user->id);
        $user->isBlock();
        if (!$user->is_block) {
            Yii::$app->response->statusCode = 200;
            $answer = [
                'data' => [
                    'status' => 'Ok',
                    'user' => $user,
                    'role' => $user->roles->title,
                ]
            ];
        } else {
            Yii::$app->response->statusCode = 401;
            $answer = [
                'error' => [
                    'code' => 401
                ],
            ];
        }
        return $this->asJson($answer);
    }
}
