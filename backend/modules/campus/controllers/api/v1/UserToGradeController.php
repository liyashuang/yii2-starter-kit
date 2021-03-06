<?php

namespace backend\modules\campus\controllers\api\v1;

/**
* This is the class for REST controller "UserToGradeController".
*/

use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use backend\modules\campus\models\search\UserToGradeSearch;
use backend\modules\campus\models\UserToGrade;
class UserToGradeController extends \yii\rest\ActiveController
{
    public $modelClass = 'backend\modules\campus\models\UserToGrade';
    /**
    * @inheritdoc
    */
    public function behaviors()
    {
        
            return ArrayHelper::merge(
                parent::behaviors(),
                [
                    'access' => [
                        'class' => AccessControl::className(),
                        'rules' => [[
                            'allow' => true,
                            'matchCallback' => function ($rule, $action) {return \Yii::$app->user->can($this->module->id . '_' . $this->id . '_' . $action->id, ['route' => true]);},
                        ]]
                    ]
                ]
            );
        
    } 

    /**
     * @param  [action] yii\rest\IndexAction
     * @return [type] 
     */
    public function beforeAction($action)
    {
        $format = \Yii::$app->getRequest()->getQueryParam('format', 'json');

        if($format == 'xml'){
            \Yii::$app->response->format = \yii\web\Response::FORMAT_XML;
        }else{
            \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        }

        // 移除access行为，参数为空全部移除
        // Yii::$app->controller->detachBehavior('access');
        return $action;
    }

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'],$actions['create'],$actions['update']);
        return $actions;
    }

    /**
     * @var array
     */
    public $serializer = [
        'class'              => 'common\rest\Serializer',   // 返回格式数据化字段
        'collectionEnvelope' => 'result',                   // 制定数据字段名称
        'errno'              => 0,                          // 错误处理数字
        'message'            => [ 'OK' ],                   // 文本提示
    ];

    /**
     * @SWG\Get(path="/campus/api/v1/user-to-grade/index",
     *     tags={"300-Grade-班级管理接口"},
     *     summary="查询所有班级学员",
     *     description="返回用户学员管理",
     *     produces={"application/json"},
     * @SWG\Parameter(
     *      in = "query",
     *      name = "user_to_grade_id",
     *      description = "id",
     *      required    = false,
     *      type        = "integer"
     *      
     *      ),
     * @SWG\Parameter(
     *      in = "query",
     *      name = "user_id",
     *      description = "用户id",
     *      required    = false,
     *      type        = "integer"
     *      ),  
     *         
     * @SWG\Parameter(
     *      in ="query",
     *      name = "school_id",
     *      description = "学校id",
     *      required    = false,
     *      type        = "integer"
     *      ),         
     * @SWG\Parameter(
     *      in = "query",
     *      name = "grade_id",
     *      description = "班级id",
     *      required    = false,
     *      type        = "integer"
     *      ), 
     * @SWG\Parameter(
     *      in = "query",
     *      name = "user_label",
     *      description = "根据用户名搜索",
     *      required    = false,
     *      type        = "string"
     *      ),
     * @SWG\Parameter(
     *      in = "query",
     *      name = "time_filter",
     *      description = "根据更新或者创建时间进行时间搜索",
     *      required    = false,
     *      type        = "string",
     *      enum        = {"created_at","updated_at"}
     *      ),
     * @SWG\Parameter(
     *      in = "query",
     *      name = "start_time",
     *      description = "开始时间",
     *      required    = false,
     *      type        = "string",
     *      ),  
     * @SWG\Parameter(
     *      in = "query",
     *      name = "end_time",
     *      description = "结束时间",
     *      required    = false,
     *      type        = "string",
     *      ),   
     * @SWG\Parameter(
     *      in = "query",
     *      name = "user_title_id_at_grade",
     *      description = "用户在班级的描述性展示行 10学生。20老师。30家长",
     *      required    = false,
     *      type        = "integer",
     *      enum        = {10,20,30}
     *      ),
     * @SWG\Parameter(
     *      in="query",
     *      name = "grade_user_type",
     *      description = "状态 10:学生;20：老师",
     *      required    = false,
     *      type        = "integer",
     *      enum        = {10,20}
     *      ),  
     * @SWG\Parameter(
     *      in="query",
     *      name = "status",
     *      description = "状态 1:正常;0:删除;3已转办；4已退休",
     *      required    = false,
     *      type        = "integer",
     *      enum        = {0,1,3,4}
     *      ), 
     *
     * @SWG\Response(
     *         response = 200,
     *         description = "返回用户学员管理"
     *     ),
     * )
     *
    **/
    public function actionIndex(){
        $searchModel = new UserToGradeSearch;
       // $searchModel->load(\yii::$app->request->queryParams,'');
        $dataProvider = $searchModel->searchApi(\Yii::$app->request->queryParams);
        $dataProvider->sort = [
            'defaultOrder' => ['created_at' => SORT_DESC]
        ];
        return $dataProvider;
    }

    /**
     * @SWG\Get(path="/campus/api/v1/user-to-grade/view",
     *     tags={"300-Grade-班级管理接口"},
     *     summary="班级学员关系表创建",
     *     description="返回用户学员管理",
     *     produces={"application/json"},
     * @SWG\Parameter(
     *      in = "query",
     *      name = "id",
     *      description = "id",
     *      required    = true,
     *      type        = "integer"
     *      
     *      ),
     * @SWG\Response(
     *         response = 200,
     *         description = "返回用户学员管理"
     *     ),
     * )
     *
    **/
  
    /**
     * @SWG\Post(path="/campus/api/v1/user-to-grade/create",
     *     tags={"300-Grade-班级管理接口"},
     *     summary="创建班级学员关系表",
     *     description="返回班级学员",
     *     produces={"application/json"},
     * @SWG\Parameter(
     *      in = "formData",
     *      name = "user_id[]",
     *      description = "用户id",
     *      required    = true,
     *      type        = "integer"
     *      ),  
     *         
     * @SWG\Parameter(
     *      in ="formData",
     *      name = "school_id",
     *      description = "学校id",
     *      required    = true,
     *      type        = "integer"
     *      ),         
     * @SWG\Parameter(
     *      in = "formData",
     *      name = "grade_id",
     *      description = "班级id",
     *      required    = true,
     *      type        = "integer"
     *      ), 
     * @SWG\Parameter(
     *      in = "formData",
     *      name = "user_title_id_at_grade",
     *      description = "用户在班级的描述性展示行",
     *      required    = true,
     *      type        = "integer",
     *      default     = "10",
     *      enum        = {10,20,30}
     *      ), 
     * @SWG\Parameter(
     *      in="formData",
     *      name = "grade_user_type",
     *      description = "状态 10:学生;20：老师",
     *      required    = true,
     *      default     = 10,
     *      type        = "integer",
     *      enum        = {10,20}
     *      ), 
     * @SWG\Parameter(
     *      in="formData",
     *      name = "status",
     *      description = "状态 1:正常;0:删除;3已转办；4已退休",
     *      required    = true,
     *      default     = 1,
     *      type        = "integer",
     *      enum        = {0,1,3,4}
     *      ), 
     *
     * @SWG\Response(
     *         response = 200,
     *         description = "返回用户学员管理"
     *     ),
     * )
     *
    **/
    public function actionCreate()
    {
        $model = new $this->modelClass;
        if($_POST){
            $info = $model->batch_create($_POST);
            if(isset($info['error'])){
                $this->serializer['message'] = $info['error'];
            }
            return $info['message'];
        }
        $this->serializer['errno']   = 400; 
        $this->serializer['message'] = "数据不能为空";
        return [];
    }


    /**
     * @SWG\Post(path="/campus/api/v1/user-to-grade/update",
     *     tags={"300-Grade-班级管理接口"},
     *     summary="修改班级学员关系表",
     *     description="修改班级学员关系表",
     *     produces={"application/json"},
     *
     *  @SWG\Parameter(
     *      in = "formData",
     *      name = "user_to_grade_id",
     *      description = "班级关系表id",
     *      required    = true,
     *      type        = "integer"
     *      ),  
     * @SWG\Parameter(
     *      in = "formData",
     *      name = "user_id",
     *      description = "用户id",
     *      required    = true,
     *      type        = "integer"
     *      ),  
     *         
     * @SWG\Parameter(
     *      in ="formData",
     *      name = "school_id",
     *      description = "学校id",
     *      required    = true,
     *      type        = "integer"
     *      ),         
     * @SWG\Parameter(
     *      in = "formData",
     *      name = "grade_id",
     *      description = "班级id",
     *      required    = true,
     *      type        = "integer"
     *      ), 
     * @SWG\Parameter(
     *      in = "formData",
     *      name = "user_title_id_at_grade",
     *      description = "用户在班级的描述性展示行",
     *      required    = true,
     *      type        = "integer",
     *      default     = "10",
     *      enum        = {10,20,30}
     *      ), 
     * @SWG\Parameter(
     *      in="formData",
     *      name = "grade_user_type",
     *      description = "状态 10:老师;20：家长",
     *      required    = true,
     *      default     = 10,
     *      type        = "integer",
     *      enum        = {10,20}
     *      ), 
     * @SWG\Parameter(
     *      in="formData",
     *      name = "status",
     *      description = "状态 1:正常;0:删除;3已转办；4已退休",
     *      required    = true,
     *      default     = 1,
     *      type        = "integer",
     *      enum        = {0,1,3,4}
     *      ), 
     *
     * @SWG\Response(
     *         response = 200,
     *         description = "返回用户学员管理"
     *     ),
     * )
     *
    **/
    public function actionUpdate(){
        $model = UserToGrade::find((int)\Yii::$app->request->post('user_to_grade_id'));
        if(!$model){
            $this->serializer['errno'] = '422';
            $this->serializer['message'] = '数据异常';
            return [];
        }
        $model->load($_POST,'');
        $model->save();
        return $model;
    }


    /**
     * @SWG\Get(path="/campus/api/v1/user-to-grade/form-list",
     *     tags={"300-Grade-班级管理接口"},
     *     summary="创建班级学员所需要的下拉框数据",
     *     description="创建班级学员所需要的下拉框数据",
     *     produces={"application/json"},
     * @SWG\Parameter(
     *      in = "query",
     *      name = "type",
     *      description = "获取那些下拉框",
     *      required    = false,
     *      type        = "integer"
     *      ),
     * @SWG\Parameter(
     *      in = "query",
     *      name = "school_id",
     *      description = "type是6 必须传school_id",
     *      required    = false,
     *      type        = "integer"
     *      ),
     * @SWG\Response(
     *         response = 200,
     *         description = "传1 获取状态; 传2 grade_user_type 关系类型; 传3 user_title_id_at_grade用户在班级的描述性展示Title，没有逻辑; 传4 获取用户;传5 获取学校；传6跟school_id 获取班级。
             不传获取除班级以外的全部数据 "
     *     ),
     * )
     *
    **/
    public function actionFormList($type = 0,$school_id = false){
        $model = new  $this->modelClass;
        if($type == 1){
            return $model->DropDownLabel(UserToGrade::optsStatus());
        };
        if($type == 2){
            return $model->DropDownLabel(UserToGrade::optsUserType());
        };
        if($type == 3){
            return $model->DropDownLabel(UserToGrade::optsUserTitleType());
        }
        if($type == 4){
            return $model->DropDownUser();
        }
        if($type == 5){
            return $model->DropDownSchool();
        }
        if($type == 6 && isset($school_id)){
            return $model->DropDownGrade($school_id);
        }
        if($type == 0){
            return $model->DropDownGather();
        }
        $this->serializer['errno'] = '422';
        $this->serializer['message'] = '找不到数据';
        return [];
    }
}
