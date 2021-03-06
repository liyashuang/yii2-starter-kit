<?php

namespace frontend\models\gedu\resources;

use Yii;
use yii\helpers\ArrayHelper;
use frontend\models\base\CourseOrderItem as BaseCourseOrderItem;
use frontend\models\gedu\resources\Courseware;
use frontend\models\gedu\resources\Course;
use common\payment\alipay\buildermodel\AlipayTradeWapPayContentBuilder;
use common\payment\alipay\AlipayTradeService;

/**
 * This is the model class for table "couese_order_item".
 */
class CourseOrderItem extends BaseCourseOrderItem
{

public function behaviors()
    {
        return ArrayHelper::merge(
            parent::behaviors(),
            [
                # custom behaviors
            ]
        );
    }

    public function rules()
    {
        return ArrayHelper::merge(
             parent::rules(),
             [
                  [['courseware_id','course_id','coupon_type',],'integer'],
                  [['payment_id'],'string'],
                  [['order_sn'], 'string', 'max' => 32],
                  [['order_sn'], 'unique'],
                  [['order_sn'], 'required'],
             ]
        );
    }

    public function getCourse(){
        return $this->hasOne(Course::className(),['course_id'=>'course_id']);
    }


    /**
     * [processCourseOrder 处理订单]
     * @param  [type] $params [description]
     * @return [type]         [description]
     */
    public function processCourseOrder($params)
    {
        $info = [];
        $params['user_id']  = Yii::$app->user->identity->groupId();
        $params['order_sn'] = $this->builderNumber();

        // 订单数据验证
        $validate = $this->validateOrderParams($params);
        if(isset($validate['errno']) && $validate['errno'] !== 0){
            return $validate;
        }

        // 创建订单
        $info = $this->createOrderOne($validate);

        return $info;
    }

    /**
     * [validateOrderParams 验证订单数据]
     * @param  [type] $params [description]
     * @return [type]         [description]
     */
    public function validateOrderParams($params)
    {
        $info = [
            'errno'   => 0,
            'message' => ''
        ];

        // 验证课件ID
        if (!isset($params['course_id']) || empty($params['course_id'])) {
            $info['errno']   = __LINE__;
            $info['message'] = 'Course ID Can Not Be Null!';
            return $info;
        }

        // 验证订单状态
        if (!isset($params['status']) || !in_array($params['status'],[self::STATUS_VALID])) {
            $info['errno']   = __LINE__;
            $info['message'] = 'Order Status Is Not Legal!';
            return $info;
        }

        // 验证支付方式
        if (!isset($params['payment']) || !in_array($params['payment'],[self::PAYMENT_ONLINE,self::PAYMENT_ALIPAY,self::PAYMENT_WECHAT, self::PAYMENT_OFFLINE])) {
            $info['errno']   = __LINE__;
            $info['message'] = 'Payment Type Is Not Legal!';
            return $info;
        }else{
            $params['payment'] = (int) $params['payment'];
        }

        // 验证支付状态
        if (!isset($params['payment_status']) || empty($params['payment_status'])) {
            $params['payment_status'] = CourseOrderItem::PAYMENT_STATUS_NON_PAID;
        }

        // 验证订单总价和总课程数，待完善
        if (isset($params['total_price']) && !empty($params['total_price'])) {
            $course = Course::findOne($params['course_id']);
            if (!isset($course->parent_id) || empty($course->parent_id)) {
                if ($course->present_price === null) {
                    $info['errno']   = __LINE__;
                    $info['message'] = 'Course Price Data Exception! Please Contact Administrator.';
                    return $info; 
                }

                // 未验证会员价等
                $params['total_price']  = $course->present_price;
                $params['total_course'] = $course->course_counts;
            }else{
                $info['errno']   = __LINE__;
                $info['message'] = 'A (master)Course With ID '.$params['course_id'].' Does Not Exist!';
                return $info; 
            }
        }else{
            $info['errno']   = __LINE__;
            $info['message'] = 'Total Price Can Not Be Null!';
            return $info;
        }

        // 验证总课程数
        if (!isset($params['presented_course']) && empty($params['presented_course'])) {
            $params['presented_course'] = '0';
        }

        // 验证优惠类型和优惠价格
        if (isset($params['coupon_type']) && !empty($params['coupon_type']) && isset($params['coupon_price']) && !empty($params['coupon_price'])) {
            if ($params['total_price'] != $params['coupon_price']+$params['real_price']) {
                $info['errno']   = __LINE__;
                $info['message'] = 'Coupon Price Is Not Legal!';
                return $info;
            }
        }else{
            $params['coupon_price'] = 0;
        }

        // 验证实际付款，待完善
        if (isset($params['real_price']) && !empty($params['real_price'])) {
            if ($params['real_price'] != $params['total_price'] - $params['coupon_price']) {
                $info['errno']   = __LINE__;
                $info['message'] = 'Real Price Is Not Legal!';
                return $info;
            }
        }else{
            $info['errno']   = __LINE__;
            $info['message'] = 'Real Price Can Not Be Null!';
            return $info;
        }

        // 验证订单编号
        if (!isset($params['order_sn']) || empty($params['order_sn'])) {
            $info['errno']   = __LINE__;
            $info['message'] = 'Order Sn Can Not Be Null!';
            return $info;
        }

        if ($info['errno'] == 0) {
            return $params;
        }

        return $info;
    }

    /**
     * [createOrderOne 创建一个订单]
     * @param  [type] $params [description]
     * @return [type]         [description]
     */
    public function createOrderOne($params)
    {
        $info = [
            'errno'   => 0,
            'message' => ''
        ];

        $model = new $this;
        $data['CourseOrderItem'] = $params;

        if ($model->load($data) && $model->save($data)) {
            return $model;
        }

        $info['errno']   = __LINE__;
        $info['message'] = $model->getErrors();
        return $info;
    }

    /**
     * [discountRules 用户是否满足优惠规则]
     * @return [type] [description]
     */
    public function discountRules()
    {
        if (Yii::$app->user->isGuest) {
            return false;
        }

        // 首单减免
        $order_count = self::find()->where([
            'user_id' => Yii::$app->user->identity->groupId(),
            'status'  => self::STATUS_VALID,
        ])->count();
        if ($order_count == 0) {
            return true;
        }

        return false;
    }

    /**
     * [wapAlipay 支付宝手机网站支付]
     * @return [type] [返回form表单]
     */
    public function wapAlipay()
    {
        $result        = [];
        $alipay_config = Yii::$app->params['payment']['gedu']['alipay'];
        $body          = '【光大】精品课程';
        $subject       = '【光大】精品课程';

        // 检测密钥公钥
        if (!file_exists($alipay_config['merchant_private_key']) || !file_exists($alipay_config['alipay_public_key'])) {
            $result['errno']    = __LINE__;
            $result['message'] = 'The Private Key Is Not Exist!';
            return $result;
        }
        $alipay_config['merchant_private_key'] = file_get_contents($alipay_config['merchant_private_key']);
        $alipay_config['alipay_public_key']    = file_get_contents($alipay_config['alipay_public_key']);


        // 拼接同步跳转URL的参数
        // $alipay_config['return_url'] = $alipay_config['return_url'].$this->courseware_id;

        // 组装业务参数
        if ($this->course) {
            $body    = '【光大】'.$this->course->title.'(共'.$this->course->course_counts.'节课程)';
            $subject = '【光大】'.$this->course->title;
        }
        $out_trade_no    = $this->order_sn;
        $total_amount    = $this->real_price;
        $timeout_express = '1m';
        // $seller_id       = '';   // 支付宝账号对应的支付宝唯一用户号

        // 构建请求对象
        $payRequestBuilder = new AlipayTradeWapPayContentBuilder;
        $payRequestBuilder->setBody($body);
        $payRequestBuilder->setSubject($subject);
        $payRequestBuilder->setOutTradeNo($out_trade_no);
        $payRequestBuilder->setTotalAmount($total_amount);
        $payRequestBuilder->setTimeExpress($timeout_express);
        // $payRequestBuilder->setSellerId($seller_id);

        $payResponse = new AlipayTradeService($alipay_config);
        $result = $payResponse->wapPay($payRequestBuilder,$alipay_config['return_url'],$alipay_config['notify_url']);
        return $result;
    }
}
