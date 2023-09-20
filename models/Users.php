<?php

namespace app\models;

use DateTime;
use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\web\IdentityInterface;
use yii\web\UploadedFile;

/**
 * This is the model class for table "users".
 *
 * @property int $id
 * @property string $name
 * @property string $surname
 * @property string|null $patronymic
 * @property string $login
 * @property string $password
 * @property string $email
 * @property string|null $token
 * @property string|null $block_time
 * @property int $is_block
 * @property int $Roles_id
 * @property string|null $avatar
 * @property int|null $lifetime_token
 * @property string $register_at
 *
 * @property Comments[] $comments
 * @property Posts[] $posts
 * @property Reaction[] $reactions
 * @property Roles $roles
 */
class Users extends ActiveRecord implements IdentityInterface
{
    public $password_confirmation;
    public $confirm;
    public $upload_avatar;

    const SCENARIO_LOGIN = 'login';
    const SCENARIO_REGISTER = 'register';
    const SCENARIO_USERINFO = 'user-info';
    const SCENARIO_BLOCK = 'block';
    const SCENARIO_CREAT_AVATAR = 'creat-avatar';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'users';
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['register_at'],
                    // ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_at'],
                ],
                // if you're using datetime instead of UNIX timestamp:
                'value' => new Expression('NOW()'),
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['login', 'password'], 'required', 'except' => 'creat-avatar'],
            [['block_time', 'register_at'], 'safe'],
            [['is_block', 'Roles_id', 'lifetime_token'], 'integer'],
            [['name', 'surname', 'patronymic', 'login', 'password', 'email', 'token', 'avatar'], 'string', 'max' => 255],
            [['Roles_id'], 'exist', 'skipOnError' => true, 'targetClass' => Roles::class, 'targetAttribute' => ['Roles_id' => 'id']],
        
            [['name', 'surname', 'patronymic'], 'match', 'pattern' => '/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u'],
            ['patronymic', 'default', 'value' => null],
            ['email', 'email'],
            ['login', 'match', 'pattern' => '/^[А-Яа-яЁёA-Za-z]/', 'message' => 'логин должен начинаться с буквы'],
            ['password', 'match', 'pattern' => '/^(?=.*[0-9])(?=.*[a-z])(?=.*[A-Z]).{7,}$/'],
            ['avatar', 'default', 'value' => 'default.jpg'],
            [['upload_avatar'], 'image', 'extensions' => 'png, jpg'],

            [['login'], 'unique', 'on' => static::SCENARIO_REGISTER],
            [['email'], 'unique', 'on' => static::SCENARIO_REGISTER],
            [['name', 'surname', 'email', 'password_confirmation', 'confirm'], 'required', 'on' => static::SCENARIO_REGISTER],
            ['password_confirmation', 'compare', 'compareAttribute' => 'password', 'on' => static::SCENARIO_REGISTER],
            ['confirm', 'required', 'requiredValue' => 'true', 'on' => static::SCENARIO_REGISTER, 'message' => 'Нужно подтверждение согласия на обработку персональных данных'],

            ['block_time', 'required', 'on' => static::SCENARIO_BLOCK],
            ['block_time', 'validateTimeBlock', 'on' => static::SCENARIO_BLOCK],

            [['login'], 'required', 'on' => static::SCENARIO_CREAT_AVATAR],


        ];
    }

    public function validateTimeBlock($attribute)
    {
        if ((new DateTime($this->$attribute))->getTimestamp() < (new DateTime())->getTimestamp()) {
            $this->addError($attribute, 'Увеличьте время блокировки');
        }

        if ((new DateTime($this->$attribute))->getTimestamp() > (new DateTime('2038-01-19 03:14:07'))->getTimestamp()) {
            $this->addError($attribute, 'Уменьшите время блокировки');
        }
    }

    public function uploadFile()
    {
        if ($this->validate()) {
            $nameFile = 'avatar_user_' . Yii::$app->security->generateRandomString(10) . '.' . $this->imageFile->extension;
            if ($this->upload_avatar->saveAs(Yii::getAlias('@app') . "/../indexBlogSite/images/avatars/" . $nameFile)) {
                return $nameFile;
            }
        }
        return false;
    }

    public function validatePassword($password)
    {
        return Yii::$app->getSecurity()->validatePassword($this->password, $password);
    }

    public function isBlock()
    {
        if ($this->is_block) {
            if ((new DateTime($this->block_time))->getTimestamp() < (new DateTime())->getTimestamp()) {
                $this->is_block = 0;
                $this->block_time = null;
                $this->save(false);
            }
        }
        return $this->is_block;
    }

    public function deleteUser()
    {
        foreach($this->posts as $post) {
            $post->deletePost();
        }

        foreach($this->comments as $comment) {
            $comment->deleteAnswer();
        }
    }

    public function creatAvatar($titleAvatar)
    {
        if ($titleAvatar == 'default.jpg' || $titleAvatar == 'undefined') {
            $nameFile = 'avatar_user_' . Yii::$app->security->generateRandomString(10) . '.png';
        } else {
            $nameFile = $titleAvatar;
        }

        return $this->createImage($nameFile, $this->login);
    }

    public function createImage($nameFile, $login)
    {
        $image = imageCreate(100,100);

        $background = imagecolorallocate($image, rand(0, 255), rand(0, 255), rand(0, 255));
        $black = imagecolorallocate($image, 0, 0, 0);

        $white = imagecolorallocate($image, 255, 255, 255);

        $string = strtoupper($login[0]);
        $size_test = 50;
        $angle_test = 0;

        $font = Yii::getAlias('@app') . "/../indexBlogSite/fonts/arial/ArialBold.ttf";

        $box = imagettfbbox($size_test, $angle_test, $font, $string);

        $x = intval((imagesx($image) - $box[4]) / 2);
        $y = intval((imagesy($image) - $box[5]) / 2);

        $this->imagettfstroketext($image, $size_test, $angle_test, $x, $y, $black, $white, $font, $string, 1);

        if (imagepng($image, Yii::getAlias('@app') . "/../indexBlogSite/images/avatars/" . $nameFile)) {
            imagedestroy($image);
            return $nameFile;
        }
        return false;
    }

    public function imagettfstroketext(&$image, $size, $angle, $x, $y, &$textcolor, &$strokecolor, $fontfile, $text, $px) {
        for($c1 = ($x-abs($px)); $c1 <= ($x+abs($px)); $c1++)
            for($c2 = ($y-abs($px)); $c2 <= ($y+abs($px)); $c2++)
                $bg = imagettftext($image, $size, $angle, $c1, $c2, $strokecolor, $fontfile, $text);
       return imagettftext($image, $size, $angle, $x, $y, $textcolor, $fontfile, $text);
    }

    public static function getUsers($flag = 'sort-desc-id', $options = null)
    {
        $users = static::find();

        if ($options) {
            $users = $users->orWhere(['id' => $options])->orWhere(['like', 'name', $options])->orWhere(['like', 'surname', $options])->orWhere(['like', 'login', $options])->orWhere(['like', 'email', $options]);
        }
        
        switch ($flag) {
            case "sort-desc-id":
                $users = $users->orderBy(['id' => SORT_DESC]);
                break;
            case "sort-asc-id":
                $users = $users->orderBy(['id' => SORT_ASC]);
                break;
            case "sort-desc-name":
                $users = $users->orderBy(['name' => SORT_DESC]);
                break;
            case "sort-asc-name":
                $users = $users->orderBy(['name' => SORT_ASC]);
                break;
            case "sort-desc-surname":
                $users = $users->orderBy(['surname' => SORT_DESC]);
                break;
            case "sort-asc-surname":
                $users = $users->orderBy(['surname' => SORT_ASC]);
                break;
            case "sort-desc-login":
                $users = $users->orderBy(['login' => SORT_DESC]);
                break;
            case "sort-asc-login":
                $users = $users->orderBy(['login' => SORT_ASC]);
                break;
            case "sort-desc-email":
                $users = $users->orderBy(['email' => SORT_DESC]);
                break;
            case "sort-asc-email":
                $users = $users->orderBy(['email' => SORT_ASC]);
                break;
            case "sort-desc-active":
                $users = $users->orderBy(['token' => SORT_DESC, 'id' => SORT_DESC]);
                break;
            case "sort-asc-active":
                $users = $users->orderBy(['token' => SORT_ASC, 'id' => SORT_DESC]);
                break;
            case "sort-desc-block-time":
                $users = $users->orderBy(['block_time' => SORT_DESC, 'id' => SORT_DESC]);
                break;
            case "sort-asc-block-time":
                $users = $users->orderBy(['block_time' => SORT_ASC, 'id' => SORT_DESC]);
                break;
        }

        $users = $users->all();

        foreach ($users as $user) {
            $user->isBlock();
        }

        return $users;
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'surname' => 'Surname',
            'patronymic' => 'Patronymic',
            'login' => 'Login',
            'password' => 'Password',
            'email' => 'Email',
            'token' => 'Token',
            'block_time' => 'Block Time',
            'is_block' => 'Is Block',
            'Roles_id' => 'Roles ID',
            'avatar' => 'Avatar',
            'lifetime_token' => 'Lifetime Token',
            'register_at' => 'Register At',
        ];
    }

    /**
     * Gets query for [[Comments]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getComments()
    {
        return $this->hasMany(Comments::class, ['Users_id' => 'id']);
    }

    /**
     * Gets query for [[Posts]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPosts()
    {
        return $this->hasMany(Posts::class, ['Users_id' => 'id']);
    }

    /**
     * Gets query for [[Reactions]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getReactions()
    {
        return $this->hasMany(Reaction::class, ['Users_id' => 'id']);
    }

    /**
     * Gets query for [[Roles]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRoles()
    {
        return $this->hasOne(Roles::class, ['id' => 'Roles_id']);
    }

    /**
     * IdentityInterface
     */
    public static function findIdentity($id)
    {
        return static::findOne($id);
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        return static::findOne(['token' => $token]);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAuthKey()
    {
        return $this->authKey;
    }

    public function validateAuthKey($authKey)
    {
        return $this->authKey === $authKey;
    }
}
