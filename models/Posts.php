<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * This is the model class for table "posts".
 *
 * @property int $id
 * @property string $create_at
 * @property int $Users_id
 * @property string $content
 * @property string $title
 * @property string $preview
 * @property string $update_at
 * @property string|null $image
 * @property int $views
 *
 * @property Comments[] $comments
 * @property Reaction[] $reactions
 * @property Users $users
 */
class Posts extends ActiveRecord
{
    public $upload_image_post;
    public $a;

    const SCENARIO_CREATEPOST = 'createPost';

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['create_at', 'update_at'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['update_at'],
                ],
                // if you're using datetime instead of UNIX timestamp:
                'value' => new Expression('NOW()'),
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'posts';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['create_at', 'update_at'], 'safe'],
            [['Users_id', 'content', 'title', 'preview'], 'required'],
            [['Users_id', 'views'], 'integer'],
            [['content'], 'string'],
            [['title', 'preview', 'image'], 'string', 'max' => 255],
            [['Users_id'], 'exist', 'skipOnError' => true, 'targetClass' => Users::class, 'targetAttribute' => ['Users_id' => 'id']],
            [['upload_image_post'], 'image', 'extensions' => 'png, jpg'],
        ];
    }

    public function uploadFile()
    {
        if ($this->validate()) {
            $nameFile = 'image_post_' . Yii::$app->security->generateRandomString(10) . '.' . $this->upload_image_post->extension;
            if ($this->upload_image_post->saveAs(Yii::getAlias('@app') . "/../indexBlogSite/images/posts/" . $nameFile)) {
                return $nameFile;
            }
        }
        return false;
    }

    public function validateUserID($id)
    {
        return $this->Users_id == $id;
    }

    public function deletePost()
    {
        if ($this->image && file_exists(Yii::getAlias('@app') . "/../indexBlogSite/images/posts/" . $this->image)) {
            unlink(Yii::getAlias('@app') . "/../indexBlogSite/images/posts/" . $this->image);
        }
        foreach ($this->reactions as $react) {
            $react->delete();
        }
        foreach ($this->comments as $comment) {
            $comment->deleteAnswer();
        }
        $this->delete();
    }

    public static function getPaginaction($countAllPosts, $countPostsOnePage, $actionPage)
    {
        $paginaction = [];
        $countAllPage = ceil($countAllPosts/$countPostsOnePage);
        $leftPage = $actionPage < 4 ? 1 : ($countAllPage - $actionPage <= 2 ? $countAllPage - 4 : $actionPage - 2);
        $rightPage = $actionPage < 4 ? ($countAllPage >= 5 ? 5 : $countAllPage) : ($actionPage + 2 >= $countAllPage ? $countAllPage : $actionPage + 2);
        $paginaction = range($leftPage, $rightPage);
        return $paginaction;
    }

    public static function getPosts($flag = 'desc_create_time', $options = null, $limit = null, $offset = null, $where = null)
    {
        $posts = static::find()
            ->select([
                'posts.id', 'Users_id', 'title', 'preview', 'content', 'create_at', 'update_at', 'image', 'views'
            ])
            ->addSelect("(SELECT count(*) FROM Comments as c WHERE c.Posts_id = posts.id) as countComments")
            ->addSelect("(SELECT count(*) FROM reaction as r WHERE r.Posts_id = posts.id AND reaction='1') as countLike")
            ->addSelect("(SELECT count(*) FROM reaction as r WHERE r.Posts_id = posts.id AND reaction='0') as countDislike")
            ->innerJoin('users', 'users.id = posts.Users_id');

        if ($where) {
            $posts = $posts->where($where);
        }

        if ($options) {
            $posts = $posts->orWhere(['like', 'login', $options])->orWhere(['like', 'title', $options])->orWhere(['like', 'preview', $options]);
        }
        
        switch ($flag) {
            case "desc_create_time":
                $posts = $posts->orderBy(['create_at' => SORT_DESC]);
                break;
            case "asc_create_time":
                $posts = $posts->orderBy(['create_at' => SORT_ASC]);
                break;
            case "desc_title":
                $posts = $posts->orderBy(['title' => SORT_DESC]);
                break;
            case "asc_title":
                $posts = $posts->orderBy(['title' => SORT_ASC]);
                break;
            case "desc_count_comments":
                $posts = $posts->orderBy(['countComments' => SORT_DESC, 'create_at' => SORT_DESC]);
                break;
            case "asc_count_comments":
                $posts = $posts->orderBy(['countComments' => SORT_ASC, 'create_at' => SORT_DESC]);
                break;
            case "desc_count_views":
                $posts = $posts->orderBy(['views' => SORT_DESC, 'create_at' => SORT_DESC]);
                break;
            case "asc_count_views":
                $posts = $posts->orderBy(['views' => SORT_ASC, 'create_at' => SORT_DESC]);
                break;
            case "desc_count_like":
                $posts = $posts->orderBy(['countLike' => SORT_DESC, 'create_at' => SORT_DESC]);
                break;
            case "desc_count_dislike":
                $posts = $posts->orderBy(['countDislike' => SORT_DESC, 'create_at' => SORT_DESC]);
                break;
        }

        if ($limit) {
            $posts = $posts->limit($limit);
        }

        if ($offset) {
            $posts = $posts->offset($offset);
        }

        $posts = $posts->asArray()->all();

        return $posts;
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'create_at' => 'Create At',
            'Users_id' => 'Users ID',
            'content' => 'Content',
            'title' => 'Title',
            'preview' => 'Preview',
            'update_at' => 'Update At',
            'image' => 'Image',
            'views' => 'Views',
        ];
    }

    /**
     * Gets query for [[Comments]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getComments()
    {
        return $this->hasMany(Comments::class, ['Posts_id' => 'id']);
    }

    /**
     * Gets query for [[Reactions]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getReactions()
    {
        return $this->hasMany(Reaction::class, ['Posts_id' => 'id']);
    }

    /**
     * Gets query for [[Users]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUsers()
    {
        return $this->hasOne(Users::class, ['id' => 'Users_id']);
    }
}
