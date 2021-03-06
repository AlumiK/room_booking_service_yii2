<?php

namespace frontend\controllers;

use Yii;
use yii\db\StaleObjectException;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use common\models\Application;
use common\models\ApplicationSearch;
use common\models\Room;
use yii\web\Response;

class ApplicationController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
            'access' => [
                'class' => AccessControl::class,
                'only' => ['index', 'view', 'conflict-detail', 'update', 'delete', 'print'],
                'rules' => [
                    [
                        'actions' => ['index', 'view', 'conflict-detail', 'update', 'delete', 'print'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    /**
     * 列出我的预约
     *
     * @return string
     */
    public function actionIndex()
    {
        $searchModel = new ApplicationSearch();
        $searchModel->start_time_picker = date('Y-m-d H:i');
        $searchModel->end_time_picker = date('Y-m-d H:i', time() + 3600 * 24 * 30);
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider->query->andFilterWhere(['applicant_id' => Yii::$app->user->identity->getId()]);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * 我的预约申请详情
     *
     * @param integer $id
     * @return string
     * @throws NotFoundHttpException
     * @throws ForbiddenHttpException
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);

        if ($model->applicant_id !== Yii::$app->user->identity->getId()) {
            throw new ForbiddenHttpException('你无权进行此操作。');
        }

        if (!empty($model->getConflictId()) && $model->status == Application::STATUS_PENDING && $model->canUpdate()) {
            Yii::$app->session->setFlash('error', "该申请与该房间某些已批准的申请冲突，请考虑修改申请并重新提交或与老师进行协调。");
        }

        if ($model->room->available == Room::STATUS_UNAVAILABLE && $model->canUpdate()) {
            Yii::$app->session->addFlash('error', "该申请所预约的房间已不可用。");
        }

        return $this->render('view', [
            'model' => $model,
        ]);
    }

    /**
     * 查看与自己申请相冲突的申请详情
     *
     * @param integer $id
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionConflictDetail($id)
    {
        return $this->render('conflict_detail', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * 修改我的申请
     * 如果操作成功转到详情页
     *
     * @param integer $id
     * @return string|Response
     * @throws NotFoundHttpException
     * @throws ForbiddenHttpException
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->applicant_id !== Yii::$app->user->identity->getId()) {
            throw new ForbiddenHttpException('你无权进行此操作。');
        }

        $model->start_time = date('Y-m-d H:i', $model->start_time);
        $model->end_time = date('Y-m-d H:i', $model->end_time);

        if ($model->load(Yii::$app->request->post())) {
            $model->start_time = strtotime($model->start_time);
            $model->end_time = strtotime($model->end_time);
            $model->status = Application::STATUS_PENDING;
            if ($model->save()) {
                return $this->redirect(['view', 'id' => $model->id]);
            }
            $model->start_time = date('Y-m-d H:i', $model->start_time);
            $model->end_time = date('Y-m-d H:i', $model->end_time);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * 撤销我的申请
     * 如果操作成功转到我的申请列表
     *
     * @param integer $id
     * @return Response
     * @throws NotFoundHttpException
     * @throws StaleObjectException
     * @throws ForbiddenHttpException
     * @throws \Throwable
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);

        if ($model->applicant_id !== Yii::$app->user->identity->getId()) {
            throw new ForbiddenHttpException('你无权进行此操作。');
        } else {
            $model->delete();
        }

        return $this->redirect(['index']);
    }

    /**
     * 预留打印接口
     *
     * @param integer $id
     * @return string
     * @throws NotFoundHttpException
     * @throws ForbiddenHttpException
     */
    public function actionPrint($id)
    {
        $model = $this->findModel($id);

        if ($model->applicant_id !== Yii::$app->user->identity->getId()) {
            throw new ForbiddenHttpException('你无权进行此操作。');
        }

        return $this->render('print', [
            'model' => $model,
        ]);
    }

    /**
     * 根据主键寻找申请模型
     * 如果未找到模型，抛出404异常
     *
     * @param integer $id
     * @return Application
     * @throws NotFoundHttpException
     */
    protected function findModel($id)
    {
        if (($model = Application::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('你所请求的页面不存在。');
    }
}
