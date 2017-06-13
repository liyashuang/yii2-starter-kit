<?php
namespace frontend\controllers\gedu\v1;

use Yii;
use yii\web\Response;
use yii\data\ActiveDataProvider;
use yii\rest\ActiveController;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\helpers\Url;

use frontend\modules\api\v1\resources\Article;
use frontend\models\edu\resources\Course;
use frontend\models\edu\resources\UsersToUsers;
use frontend\models\edu\resources\Courseware;
use frontend\models\wedu\resources\Notice;

class MyController extends \common\rest\Controller
{
    /**
     * @var array
     */
    public $serializer = [
        'class' => 'common\rest\Serializer',    // 返回格式数据化字段
        'collectionEnvelope' => 'result',       // 制定数据字段名称
        // 'errno' => 0,                           // 错误处理数字
        'message' => 'OK',                      // 文本提示
    ];

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

    /**
     * @param  [type]
     * @param  [type]
     * @return [type]
     */
    public function afterAction($action, $result){
        $result = parent::afterAction($action, $result);

        return $result;
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['contentNegotiator']['formats']['text/html'] = Response::FORMAT_HTML;
        return $behaviors;
    }

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index']);
        return $actions;
    }

    /**
     * @SWG\Get(path="/my/course",
     *     tags={"700-My-我的页面接口"},
     *     summary="我的课程[静态数据]",
     *     description="返回我的课程列表",
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response = 200,
     *         description = "无需填写，直接返回数据"
     *     ),
     * )
     *
    **/

    public function actionCourse()
    {
        if(Yii::$app->user->isGuest){
            $this->serializer['errno']   = 422;
            $this->serializer['message'] = '请您先登录';
            return [];
        }

        $model = [];
        $modelClass = new Courseware;

        // 需要用user_id查询已购买/已收藏/已上完的课程
        $model = $modelClass::find()
            // 状态失效(下架)的课程仅展示
            // ->where(['status' => $modelClass::COURSEWARE_STATUS_VALID])
            ->andWhere(['category_id' => 14])
            ->andWhere(['courseware_id' => $modelClass->prentCourseware()])
            ->all();

        return $model;

    }

    /**
     * @SWG\Get(path="/my/notice",
     *     tags={"700-My-我的页面接口"},
     *     summary="我的消息",
     *     description="返回我的消息列表",
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response = 200,
     *         description = "无需填写，直接返回数据"
     *     ),
     * )
     *
    **/

    public function actionNotice()
    {
        if(Yii::$app->user->isGuest){
            $this->serializer['errno']   = 422;
            $this->serializer['message'] = '请您先登录';
            return [];
        }

        $data  = [];
        $model = Notice::find()->where([
            'receiver_id' => Yii::$app->user->identity->id,
            'status_send' => Notice::STATUS_SEND_SENT
        ])->asArray()->all();

        if ($model) {
            foreach ($model as $key => $value) {
                $value['sender_name']   = Notice::getUserName($value['sender_id']);
                $value['receiver_name'] = Notice::getUserName($value['receiver_id']);
                $data[] = $value;
            }
        }

        return $data;
    }

    /**
     * @SWG\Get(path="/my/grade",
     *     tags={"700-My-我的页面接口"},
     *     summary="我的成绩-返回年级列表",
     *     description="返回我的年级列表",
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response = 200,
     *         description = "无需填写，直接返回数据"
     *     ),
     * )
     *
    **/

    public function actionGrade()
    {
        if(Yii::$app->user->isGuest){
            $this->serializer['errno']   = 422;
            $this->serializer['message'] = '请您先登录';
            return [];
        }

        $data   = [];
        $temp   = [];

        $grade_name = [
            1 => '燕郊在线商学院一年级',
            2 => '飞翔的小鸟',
            3 => '周思思老师班',
        ];

        $owner_id = [
            1 => '马云',
            2 => '马化腾',
            3 => '马卡洛夫',
        ];


        for ($i=1; $i < 4; $i++) { 
            $time = rand(1999,2018);
            $temp = [
                'grade_id'           => $i,
                'grade_name'         => $grade_name[$i],
                'owner_id'           => $owner_id[$i],
                'graduate'           => rand(0,1),
                'time_of_graduation' => $time,
                'time_of_enrollment' => $time-3,
                'target_url'         => Yii::$app->request->hostInfo.Url::to(['gedu/v1/my/score','grade_id' => $i]),
            ];
            $data[] = $temp;
        }

        return $data;
    }

    /**
     * @SWG\Get(path="/my/score",
     *     tags={"700-My-我的页面接口"},
     *     summary="我的成绩-返回成绩",
     *     description="返回我的课程对应成绩",
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *        in = "query",
     *        name = "grade_id",
     *        description = "年级/班级ID",
     *        required = true,
     *        type = "string",
     *        default = "1",
     *     ),
     *     @SWG\Response(
     *         response = 200,
     *         description = "无需填写，直接返回数据"
     *     ),
     * )
     *
    **/
    public function actionScore($grade_id)
    {
        if(Yii::$app->user->isGuest){
            $this->serializer['errno']   = 422;
            $this->serializer['message'] = '请您先登录';
            return [];
        }

        if (!$grade_id) {
            $this->serializer['errno']   = 422;
            $this->serializer['message'] = '缺少参数grade_id';
            return [];
        }

        $data  = $temp   = [];
        $files = $params = [];

        $file_url = [
            1 => 'http://static.v1.wakooedu.com/o_1bd35cueh14i216941t4m2gr1e439.jpg',
            2 => 'http://static.v1.wakooedu.com/o_1bdaq9i2n1vdmu5g1ods1lrq15i6d.jpg',
            3 => 'http://static.v1.wakooedu.com/o_1bdaq9i2n1pqr1a3k9uu1vd3cric.jpg',
            4 => 'http://static.v1.wakooedu.com/o_1bdaq9i2n1g6lsd9oefp2mlceb.jpg',
        ];

        for ($i=1; $i < 5; $i++) { 
            $params = [
                'file_id'  => $i,
                'file_url' => $file_url[$i]
            ];
            $files[] = $params;
        }

        $course_title = [
            1 => '英语十六级',
            2 => '语文小测验',
            3 => '数学期末考试',
            4 => '课堂平时成绩',
            5 => '毕业考综合项目',
        ];

        for ($i=1; $i < 6; $i++) {
            $temp = [
                'course_id'    => $i,
                'course_title' => $course_title[$i],
                'full_mark'    => 100,
                'score'        => rand(50,100),
                'files'        => $files,
            ];
            $data[] = $temp;
        }

        return $data;
    }


    /**
     * @SWG\Get(path="/my/honor",
     *     tags={"700-My-我的页面接口"},
     *     summary="我的荣誉",
     *     description="返回我的荣誉列表",
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response = 200,
     *         description = "无需填写，直接返回数据"
     *     ),
     * )
     *
    **/

    public function actionHonor()
    {
        if(Yii::$app->user->isGuest){
            $this->serializer['errno']   = 422;
            $this->serializer['message'] = '请您先登录';
            return [];
        }

        $data  = [];
        $temp  = [];

        $title = [
            1 => '再来一瓶幸运奖',
            2 => 'YJZX LOL世界总冠军',
            3 => '书法大赛一等奖',
        ];
        $detail = [
            1 => '康师傅冰红茶再来一瓶奖',
            2 => '第一届YJZX英雄联盟比赛世界总冠军',
            3 => '第一三五七届全国书法大赛一等奖',
        ];

        $rewardsbureau = [
            1 => 'O2O社区超市第10086店',
            2 => '三河市物联网络技术研发有限公司',
            3 => '中国书法爱好者协会',
        ];
        $img = [
            1 => 'http://static.v1.wakooedu.com/o_1bhtk7tba15j71ls81jo9kl85gb9.png',
            2 => 'http://static.v1.wakooedu.com/o_1bhtknjdehqdu42b831f9l164i9.png',
            3 => 'http://static.v1.wakooedu.com/o_1bhtk6b0s1k1qu861u2g1knf1hv9.png',
        ];

        for ($i=1; $i < 4; $i++) { 
            $temp['honor_id']      = $i;
            $temp['owner_id']      = Yii::$app->user->identity->id;
            $temp['owner']         = Yii::$app->user->identity->username;
            $temp['honor_name']    = $title[$i];
            $temp['honor_detial']  = $detail[$i];
            $temp['imgUrl']        = $img[$i];
            $temp['rewardsbureau'] = $rewardsbureau[$i];
            $temp['issue_date']    = time();

            $data[] = $temp;
            $temp   = [];
        }

        return $data;
    }


}