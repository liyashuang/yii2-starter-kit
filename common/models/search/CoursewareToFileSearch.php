<?php

namespace common\models\search;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\courseware\CoursewareToFile;

/**
 * CoursewareToFileSearch represents the model behind the search form about `common\models\courseware\CoursewareToFile`.
 */
class CoursewareToFileSearch extends CoursewareToFile
{
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['courseware_to_file_id', 'school_id', 'grade_id', 'status', 'sort', 'updated_at', 'created_at'], 'integer'],
            [['title'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = CoursewareToFile::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        if (!($this->load($params) && $this->validate())) {
            return $dataProvider;
        }

        $query->andFilterWhere([
            'courseware_to_file_id' => $this->courseware_to_file_id,
            'school_id' => $this->school_id,
            'grade_id' => $this->grade_id,
            'status' => $this->status,
            'sort' => $this->sort,
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
        ]);

        $query->andFilterWhere(['like', 'title', $this->title]);

        return $dataProvider;
    }
}