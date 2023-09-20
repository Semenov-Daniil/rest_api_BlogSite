<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "reaction".
 *
 * @property int $id
 * @property int $Posts_id
 * @property int $Users_id
 * @property int|null $reaction
 *
 * @property Posts $posts
 * @property Users $users
 */
class Reaction extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'reaction';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['Posts_id', 'Users_id'], 'required'],
            [['Posts_id', 'Users_id', 'reaction'], 'integer'],
            [['Posts_id'], 'exist', 'skipOnError' => true, 'targetClass' => Posts::class, 'targetAttribute' => ['Posts_id' => 'id']],
            [['Users_id'], 'exist', 'skipOnError' => true, 'targetClass' => Users::class, 'targetAttribute' => ['Users_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'Posts_id' => 'Posts ID',
            'Users_id' => 'Users ID',
            'reaction' => 'Reaction',
        ];
    }

    /**
     * Gets query for [[Posts]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPosts()
    {
        return $this->hasOne(Posts::class, ['id' => 'Posts_id']);
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
