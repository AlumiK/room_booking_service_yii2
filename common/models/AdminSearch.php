<?php

namespace common\models;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * 管理员筛选模型
 */
class AdminSearch extends Admin
{
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id', 'created_at', 'updated_at'], 'integer'],
            [['admin_id', 'admin_name', 'auth_key', 'password_hash', 'password_reset_token', 'email'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        return Model::scenarios();
    }

    /**
     * 根据查询条件生成dataProvider
     *
     * @param array $params
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = Admin::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['defaultOrder' => ['admin_id' => SORT_ASC]],
            'pagination'=>['pageSize' => 30],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere(['like', 'admin_id', $this->admin_id])
            ->andFilterWhere(['like', 'admin_name', $this->admin_name])
            ->andFilterWhere(['like', 'email', $this->email]);

        return $dataProvider;
    }
}
