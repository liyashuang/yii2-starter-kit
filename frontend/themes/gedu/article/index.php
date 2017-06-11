<?php
use yii\helpers\Html;
?>
<div class="gdu-content">
  <div class="row">
    <!-- 左边侧边栏 -->
    <?php
      echo $this->render('@frontend/themes/gedu/article/common/sidebarnew');
    ?>
    <!-- 文章内容部分 -->
    <div class="col-md-8 ">
    <div class="box box-widget geu-contentnew">
            <div class="box-header with-border ">
              <div class="">
                <span class=""><i class="fa fa-map-marker margin-r-5 text-purple"></i><a href="#">当前位置:首页>合作交流>友好学校</a></span>
              </div>
            </div>
            <div class="box-body">
               <div class="box-body">
              <ul class="todo-list ui-sortable">
              <?php foreach($dataProvider->getModels() as $key=>$value){?>
              	<li class="coperli">
                  <span class="handle ui-sortable-handle">
                    <i class="fa fa-ellipsis-v"></i>
                    <i class="fa fa-ellipsis-v"></i>
                  </span>
                  <span class="text">
                  	
                  	<?php echo Html::a(
                       $value->title,
                        ['article/view','id'=>$value['id']],
                        ['class'=>'','data-method'=>'open',]);
                    ?>
                  </span>
                  <small class="label"><i class="fa fa-clock-o"></i> <?php echo Yii::$app->formatter->asRelativeTime($value->created_at);?></small>
                  <div class="tools">
                   <?php echo Html::a(
                       "详情" ,
                        ['article/view','id'=>$value['id']],
                        ['class'=>'','data-method'=>'open',]);
                    ?>
                  </div>
                </li>
              <?php }?>
                 <?php 
		          echo \yii\widgets\LinkPager::widget([
		            'pagination'=>$dataProvider->pagination,
		            'options' => ['class' => 'pagination'],
		        ]);?>

              </ul>
                   
      
            </div>
            </div>
      </div>
    </div>
  </div>
</div>