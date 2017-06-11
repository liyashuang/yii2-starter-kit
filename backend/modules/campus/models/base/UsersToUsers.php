<?php
// This class was automatically generated by a giiant build task
// You should not change it manually as it will be overwritten on next build

namespace backend\modules\campus\models\base;
use backend\modules\campus\models\UserToSchool;
use Yii;

/**
 * This is the base-model class for table "users_to_users".
 *
 * @property integer $users_to_users_id
 * @property integer $user_left_id
 * @property integer $user_right_id
 * @property integer $status
 * @property integer $type
 * @property string $aliasModel
 */
abstract class UsersToUsers extends \yii\db\ActiveRecord
{
    CONST UTOU_STATUS_DELETE = 10;  // 标记关闭
    CONST UTOU_STATUS_OPEN   = 20;  // 有效

    CONST UTOU_TYPE_STUDENT   = 100;    // 学生
    CONST UTOU_TYPE_PARENT  = 200;    // 家长
    CONST UTOU_TYPE_TEACHER   = 500;    // 教师
    CONST UTOU_TYPE_LOGISTICS = 600;    // 后勤


    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'users_to_users';
    }

   
    public static function getDb(){
        //return \Yii::$app->modules['campus']->get('campus');
        return Yii::$app->get('campus');
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_left_id', 'user_right_id'], 'required'],
            [['user_left_id', 'user_right_id', 'status', 'type'], 'integer']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'users_to_users_id' => Yii::t('backend', 'Users To Users ID'),
            'user_left_id' => Yii::t('backend', '学生id'),
            'user_right_id' => Yii::t('backend', '家长id'),
            'status' => Yii::t('backend', '10 标记关闭 ；20 有效'),
            'type' => Yii::t('backend', '100 学生；200 家长； 300； 教师 500； 后勤 600'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeHints()
    {
        return array_merge(parent::attributeHints(), [
            'user_left_id' => Yii::t('backend', '学生id'),
            'user_right_id' => Yii::t('backend', '家长id'),
            'status' => Yii::t('backend', '10 标记关闭 ；20 有效'),
            'type' => Yii::t('backend', '100 学生；200 家长； 300； 教师 500； 后勤 600'),
        ]);
    }

    public function getUserToSchool(){
        return $this->hasOne(UserToSchool::className(),['user_id'=>'user_left_id']);
    }
    
    /**
     * 用户所在的班级
     * @return [type] [description]
     */
    public function getUserToGrade(){
        return $this->hasOne(backend\modules\campus\models\UserToGrade::className(),['user_id'=>'user_left_id']);
    }

    /**
     * 获取所在的班级
     */
    /*
    public function getGrade(){
        if($this->userToGrade){
            return $this->userToGrade->grade->grade_name . $this->userToGrade->school->school_title;
        }elseif($this->userToGrade){
            return $this->userToGrade->school->school_title;
        }
    }
    */

    public static function getUserName($id)
    {
        $user = \common\models\User::findOne($id);
        $name = '';
        if(isset($user->realname) && !empty($user->realname)){
            return $user->realname;
        }
        if(isset($user->username) && !empty($user->username)){
           return $user->username;
        }
        // if(isset($user->phone_number) && !empty($user->phone_number)){
        //     return $user->phone_number;
        // }
        return $name;
    }
    
    
    /**
     * @inheritdoc
     * @return \backend\modules\campus\models\query\UsersToUsers the active query used by this AR class.
     */
    public static function find()
    {
        return new \backend\modules\campus\models\query\UsersToUsers(get_called_class());
    }


}
