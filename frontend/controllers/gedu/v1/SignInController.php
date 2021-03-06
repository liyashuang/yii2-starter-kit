<?php

namespace frontend\controllers\gedu\v1;

/**
* 注册、登陆、密码找回
*/
use yii;
use yii\web\Response;

use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\rest\OptionsAction;

use frontend\models\gedu\resources\LoginForm;
use frontend\models\gedu\resources\UserForm;
use frontend\models\gedu\resources\User;
use frontend\models\gedu\resources\UsersToUsers;

use common\models\UserProfile;
use common\models\UserToken;

use common\components\Qiniu\Auth;
use common\components\Qiniu\Storage\BucketManager;

use cheatsheet\Time;

class SignInController extends \common\components\ControllerFrontendApi
{
    public $modelClass = 'frontend\models\gedu\resources\User';

    /**
     * @var array
     */
    public $serializer = [
        'class'              => 'common\rest\Serializer',
        'collectionEnvelope' => 'result',
        // 'errno'              => 0,
        'message'            => 'OK',
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
                        'matchCallback' => function ($rule, $action) {
                            // return true;
                            // var_dump($this->module->id . '_' . $this->id . '_' . $action->id); exit();
                            return \Yii::$app->user->can(
                                $this->module->id . '_' . $this->id . '_' . $action->id, 
                                ['route' => true]
                            );
                        },
                    ]]
                ]
            ]
        );
    }

    public function actions()
    {
        return [
            'options' => OptionsAction::class,
        ];
    }

    /**
     * @SWG\Post(path="/sign-in/login",
     *     tags={"GEDU-SignIn-用户接口"},
     *     summary="用户登录[已经自测]",
     *     description="用户登录：成功返回用户信息；失败返回具体原因",
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *        in = "formData",
     *        name = "LoginForm[identity]",
     *        description = "手机号、邮箱、登录名",
     *        required = true,
     *        type = "string"
     *     ),
     *     @SWG\Parameter(
     *        in = "formData",
     *        name = "LoginForm[password]",
     *        description = "密码",
     *        required = true,
     *        type = "string"
     *     ),
     *     @SWG\Parameter(
     *        in = "formData",
     *        name = "LoginForm[rememberMe]",
     *        description = "勾选记住我：1勾选；0不勾选",
     *        required = false,
     *        type = "integer",
     *        default = 1,
     *        enum = {0,1}
     *     ),
     *     @SWG\Response(
     *         response = 200,
     *         description = "登陆成功，返回用户信息"
     *     ),
     *     @SWG\Response(
     *         response = 422,
     *         description = "Data Validation Failed 账号或密码错误",
     *         @SWG\Schema(ref="#/definitions/Error")
     *     )
     * )
     *
     */
    public function actionLogin()
    {
        // echo $aaa;
        // "x-mobile-powered-by": "IOS/5.6.14",
        // "x-mobile-powered-by": "Android/5.6.14",
        // Yii::$app->getUser()->login($user);
        // Accept-Language  zh-CN,zh;q=0.8,en-US;q=0.5,en;q=0.3
        \Yii::$app->language = 'zh-CN';
        $model = new LoginForm();
        $model->load($_POST);

        if($model->login()){
            $attrUser = $model->user->attributes;

            $attrUser['ID'] = $attrUser['id'];
            unset($attrUser['id']);

            if(isset($attrUser['password_hash'])){
                unset($attrUser['password_hash']);
            }
            $attrUser['avatar'] = '';

            $proFileUser = $model->user->userProfile;

            // 默认头像
            if(isset($proFileUser->avatar_base_url) && !empty($proFileUser->avatar_base_url))
            {
                $attrUser['avatar'] = $proFileUser->avatar_base_url.'/'.$proFileUser->avatar_path;
            }else{
                $fansMpUser = isset($model->user->fansMp) ? $model->user->fansMp : '';
                if($fansMpUser){
                    $attrUser['avatar'] = $fansMpUser->avatar;
                }else{
                    $attrUser['avatar'] = 'http://orh16je38.bkt.clouddn.com/o_1bn7gmjh51nu51dn1k0kimul5n9.jpg';
                }
            }

            // 学校班级
            $attrUser['grade_name'] = $attrUser['school_title'] =$attrUser['school_id'] ='';
            if ($model->user->getCharacterDetailes()) {
                $attrUser['grade_name'] = $model->user->getCharacterDetailes()['grade_label'];
                $attrUser['school_title'] = $model->user->getCharacterDetailes()['school_label'];
                $attrUser['school_id'] = $model->user->getCharacterDetailes()['school_id'];
            }

            // 家长关系
            $parents = UsersToUsers::find()->where([
                'user_right_id' => $model->user->id,
                'status'        => UsersToUsers::UTOU_STATUS_OPEN,
            ])->one();

            if ($parents) {
                $attrUser['type']    = UsersToUsers::UTOU_TYPE_PARENT;
                $attrUser['level']   = '荣耀王者'.'的家长';
                $attrUser['parents'] = UsersToUsers::getUserName($parents->user_left_id).'的家长';
            }else{
                $attrUser['type']    = UsersToUsers::UTOU_TYPE_STUDENT;
                $attrUser['level']   = '荣耀王者';
                $attrUser['parents'] = '';
            }
            
            return $attrUser;
        }else{
            Yii::$app->response->statusCode = 200;
            $this->serializer['errno']      = 1;
            $this->serializer['message']    = $model->getErrors();
            return $this->serializer['message'];
        }
    }


    /**
     * @SWG\Get(path="/sign-in/index",
     *     tags={"GEDU-SignIn-用户接口"},
     *     summary="登陆请求验证已经登陆[已经自测]",
     *     description="验证是否已经登陆。已登录则返回用户信息",
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response = 200,
     *         description = "登陆验证成功，返回登录用户信息"
     *     ),
     *     @SWG\Response(
     *         response = 422,
     *         description = "登陆验证失败，返回失败信息"
     *     )
     * )
     *
     */
    /**
     * [actionIndex description]
     * @return [type] [description]
     */
    public function actionIndex()
    {
        \Yii::$app->language = 'zh-CN';
        if(\Yii::$app->user->isGuest){
            Yii::$app->response->statusCode = 200;
            $this->serializer['errno']      = 1;
            $this->serializer['message']    = '登陆验证失败，请登录';
            return $this->serializer['message'];
        }

        $attrUser = Yii::$app->user->identity->attributes;

        if(isset($attrUser['password_hash'])){
            unset($attrUser['password_hash']);
        }
        $attrUser['avatar'] = '';
        //$account  = Yii::$app->user->identity->getAccount();

        $proFileUser = Yii::$app->user->identity->userProfile;

       // 默认头像
        if(isset($proFileUser->avatar_base_url) && !empty($proFileUser->avatar_base_url))
        {
            $attrUser['avatar'] = $proFileUser->avatar_base_url.'/'.$proFileUser->avatar_path;
        }else{
            $fansMpUser = isset($model->user->fansMp) ? $model->user->fansMp : '';
            if($fansMpUser){
                $attrUser['avatar'] = $fansMpUser->avatar;
            }else{
                $attrUser['avatar'] = 'http://orh16je38.bkt.clouddn.com/o_1bn7gmjh51nu51dn1k0kimul5n9.jpg';
            }
        }
        return $attrUser;
    }

    /**
     * @SWG\Get(path="/sign-in/send-sms",
     *     tags={"GEDU-SignIn-用户接口"},
     *     summary="发送验证码[已经自测]",
     *     description="发送验证码，成功返回验证码与用户信息",
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *        in = "query",
     *        name = "phone_number",
     *        description = "手机号",
     *        required = true,
     *        type = "string"
     *     ),
     *     @SWG\Parameter(
     *        in = "query",
     *        name = "type",
     *        description = "验证码类型：signup注册；repasswd重置密码。默认signup",
     *        required = true,
     *        type = "string",
     *        enum = {"signup", "repasswd"}
     *     ),
     *     @SWG\Response(
     *         response = 200,
     *         description = "发送成功，返回验证码和手机号",
     *     )
     * )
     *
     */
    /**
     * [actionSendSms 发送验证码]
     * @param  [type] $phone_number [手机号]
     * @param  string $type         [验证码类型]
     * @return [type]               [description]
     */
    public function actionSendSms($phone_number, $type='signup')
    {
        // var_dump($type);exit;
        \Yii::$app->language = 'zh-CN';

        if (!$phone_number) {
            $this->serializer['errno']   = 1;
            $this->serializer['message'] = '手机号不能为空';
            return $this->serializer['message'];
        }

        $user = User::find()->where([
            'phone_number' => $phone_number,
        ])->one();
        $type = ($type == 'signup') ? UserToken::TYPE_PHONE_SIGNUP : UserToken::TYPE_PHONE_REPASSWD;
        
        if (!$user) {
            if ($type == UserToken::TYPE_PHONE_SIGNUP) {
                // 创建未激活用户
                $user = new User;
                $user->phone_number = $phone_number;
                $user->status       = User::STATUS_NOT_ACTIVE;
                $user->setPassword(UserToken::randomCode(6));
                if(!$user->save()) {
                    $this->serializer['errno']   = 1;
                    $this->serializer['message'] = $user->getErrors();
                    return $this->serializer['message'];
                }
                $user->afterSignup();
            }else{
                // 用户不存在
                $this->serializer['errno']   = 1;
                $this->serializer['message'] = '该手机号码还未注册';
                return $this->serializer['message'];
            }
        }else{
            if ($user->status == User::STATUS_NOT_ACTIVE && $type == UserToken::TYPE_PHONE_REPASSWD) {
                // 用户账户未激活
                $this->serializer['errno']   = 1;
                $this->serializer['message'] = '该手机号码还未注册';
                return $this->serializer['message'];
            }elseif($user->status == User::STATUS_ACTIVE && $type == UserToken::TYPE_PHONE_SIGNUP){
                // 用户已存在
                $this->serializer['errno']   = 1;
                $this->serializer['message'] = '该手机号码已经注册过了';
                return $this->serializer['message'];
            }
        }

        $token = UserToken::find()->where([
            'user_id' => $user->id
        ])->andWhere([
            'type' => $type
        ])->one();

        if ($token) {
            $token->delete();
        }

        $code  = UserToken::randomCode();
        $token = UserToken::create(
            $user->id,
            $type,
            Time::SECONDS_IN_A_DAY,
            $code
        );
        $info = [
            'message' => '验证码'.$code.'，如非本人操作，请忽略本条短信。',
            'phone' => $user->phone_number,
        ];
        if($token){
            // 发送短信
            ymSms($info);
        }
        return $info;
    }

    /**
     * @SWG\Post(path="/sign-in/signup",
     *     tags={"GEDU-SignIn-用户接口"},
     *     summary="用户注册及重置密码[已经自测]",
     *     description="成功返回用户信息，失败返回具体原因",
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *        in = "formData",
     *        name = "UserForm[phone_number]",
     *        description = "手机号",
     *        required = true,
     *        type = "string"
     *     ),
     *     @SWG\Parameter(
     *        in = "formData",
     *        name = "UserForm[password]",
     *        description = "密码",
     *        required = true,
     *        type = "string"
     *     ),
     *     @SWG\Parameter(
     *        in = "formData",
     *        name = "UserForm[token]",
     *        description = "验证码",
     *        required = true,
     *        type = "string"
     *     ),
     *     @SWG\Parameter(
     *        in = "formData",
     *        name = "UserForm[client_type]",
     *        description = "客户端注册类型:移动端默认app",
     *        required = true,
     *        type = "string",
     *        default = "app",
     *        enum = {"app", "pc"}
     *     ),
     *     @SWG\Parameter(
     *        in = "formData",
     *        name = "UserForm[type]",
     *        description = "发送验证码类型：signup注册；repasswd重置密码。默认signup",
     *        required = true,
     *        type = "string",
     *        default = "signup",
     *        enum = {"signup", "repasswd"}
     *     ),
     *     @SWG\Response(
     *         response = 200,
     *         description = "成功，返回用户信息"
     *     ),
     *     @SWG\Response(
     *         response = 422,
     *         description = "失败，返回具体原因"
     *     )
     * )
     *
     */
    /**
     * [actionSignup 用户注册]
     * @return [type] [description]
     */
    public function actionSignup()
    {
        \Yii::$app->language = 'zh-CN';
        $model = new UserForm();
        if ($model->load(Yii::$app->request->post())) {

            $user = $model->signup();

            if (isset($user->id)) {
                if ($model->shouldBeActivated()) {
                    $this->serializer['message'] = Yii::t('frontend', '账号注册成功');
                    return $user->attributes;
                } else {
                    Yii::$app->getUser()->login($user);
                }
                return array_merge($user->attributes, ['token'=>$model->token]);
            }
        }

        Yii::$app->response->statusCode = 200;
        $this->serializer['errno']      = 1;
        $this->serializer['message']    = $model->getErrors();
        return $this->serializer['message'];
    }

    /**
     * @SWG\POST(path="/sign-in/update-profile",
     *     tags={"GEDU-SignIn-用户接口"},
     *     summary="更新用户附属信息(头像等)",
     *     description="更新用户附属表信息 http://developer.qiniu.com/docs/v6/sdk/ios-sdk.html",
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *        in = "formData",
     *        name = "user_id",
     *        description = "用户ID",
     *        required = true,
     *        type = "string"
     *     ),
     *     @SWG\Parameter(
     *        in = "formData",
     *        name = "json_data",
     *        description = "七牛返回的JSON数据",
     *        required = true,
     *        type = "string"
     *     ),
     *     @SWG\Response(
     *         response = 200,
     *         description = "更新成功"
     *     )
     * )
     *
     */
    //http://developer.qiniu.com/docs/v6/sdk/ios-sdk.html
    /*
    'yajol-static' => [
                'access_key' => 'tNgzEqpaQzZfGFJUln_9u6c7YkpFpPqFeD0zqf6_',
                'secret_key' => 'EmYNea7hf5yB4gwD7NPCR5qwbhMeKWwE38B4OTKn',
                'domain' => 'http://7xrpkx.com1.z0.glb.clouddn.com/',
                'bucket' => 'yajol-static'
            ],
    */
    /*
    {"name":"header.jpg","size":203100,"type":"image\/jpeg","hash":"FoTl-Zw-aJehckIRja4u_KHmGtYi","key":"1470045842510.jpg"}

     */
    public function actionUpdateProfile()
    {
        $avatar_base_url = Yii::$app->params['qiniu']['wakooedu']['domain'];
        //var_dump($avatar_base_url);exit;
        $user_id         = Yii::$app->request->post('user_id');
        $data            = Yii::$app->request->post('json_data');
        $data            = json_decode($data, true);

        $user = User::findOne($user_id);
        if (!$user) {
            $this->serializer['errno']   = 1;
            $this->serializer['message'] = '该用户不存在';
            return $this->serializer['message'];
        }
        $model = UserProfile::findOne($user_id);
        if($model){ // 更新
            $key = $model->avatar_path;
            if($key != $data['key']){
                $auth = new Auth(
                    \Yii::$app->params['qiniu']['wakooedu']['access_key'], 
                    \Yii::$app->params['qiniu']['wakooedu']['secret_key']
                );
                $bucketMgr = new BucketManager($auth);
                $bucket    = \Yii::$app->params['qiniu']['wakooedu']['bucket'];
                $key       = $model->avatar_path;
                $err       = $bucketMgr->delete($bucket, $key);
//var_dump($err); exit();
            }

            $model->avatar_base_url = $avatar_base_url;
            $model->avatar_path     = $data['key'];
            // $model->save(false);
        }else{ // 创建
            $model = new UserProfile();
            $model->user_id         = $user_id;
            $model->avatar_base_url = $avatar_base_url;
            $model->avatar_path     = $data['key'];
            // $model->save(false);
        }

        if (!$model->save(false)) {
            $this->serializer['errno']   = 422;
            $this->serializer['message'] = $model->getErrors();
            return [];
        }
        return ['avatar_url'=>$model->attributes['avatar_base_url'].'/'.$model->attributes['avatar_path']];
    }

     

    /**
     * @SWG\Get(path="/sign-in/qiniu-token",
     *     tags={"GEDU-SignIn-用户接口"},
     *     summary="获取七牛云Token[待开发]",
     *     description="返回七牛云上传Token",
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response = 200,
     *         description = "返回Token"
     *     )
     * )
     *
     */
    public function actionQiniuToken()
    {
        $auth = new Auth(\Yii::$app->params['qiniu']['wakooedu']['access_key'], \Yii::$app->params['qiniu']['wakooedu']['secret_key']);
        $policy['returnBody'] = '{"name": $(fname),"size": $(fsize),"type": $(mimeType),"hash": $(etag),"key":$(key)}';
        $token = $auth->uploadToken(\Yii::$app->params['qiniu']['wakooedu']['bucket'],null,3600,$policy);
        Yii::$app->response->format = Response::FORMAT_JSON;
        
       return  Yii::$app->response->data = [
            'uptoken' => $token
        ];
    }


    /**
     * @SWG\Get(path="/sign-in/logout",
     *     tags={"GEDU-SignIn-用户接口"},
     *     summary="退出用户账户",
     *     description="退出用户账户接口",
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response = 200,
     *         description = "成功返回[]，失败返回提示信息"
     *     )
     * )
     *
     */
    /**
     * @return Response
     */
    public function actionLogout()
    {
        if(Yii::$app->user->logout()){
            return [];
        }else{
            $this->serializer['errno']   = 1;
            $this->serializer['message'] = '退出失败，请重试';
            return [];
        };
    }

    public function actiolAuthKey()
    {
        $model = new LoginForm;
        if($model->load(\Yii::$app->getRequest()) && $model->login()){
            echo \Yii::$app->user->indentity->getAuthKey();
        }
    }
        
}
