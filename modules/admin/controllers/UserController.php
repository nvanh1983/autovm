<?php

namespace app\modules\admin\controllers;

use Yii;
use yii\web\Controller;
use yii\data\Pagination;
use yii\web\NotFoundHttpException;

use app\models\User;
use app\models\UserEmail;
use app\models\UserPassword;
use app\components\MultiForm;
use app\modules\admin\filters\OnlyAdminFilter;
use app\models\searchs\searchUser;
use app\models\Vps;
use yii\data\ActiveDataProvider;
use yii\helpers\Html;

class UserController extends Controller
{
    public function behaviors()
    {
        return [
            OnlyAdminFilter::className(),
        ];
    }
    
    public function actionIndex()
    {
        $searchModel = new searchUser();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        
        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
        ]);
    }
    
    public function actionCreate()
    {
        $forms = new MultiForm;
        
        $forms->user = new User;
        $forms->email = new UserEmail;
        $forms->password = new UserPassword;

        if (($data = Yii::$app->request->post()) && $forms->validate($data)) {
            
            $transaction = Yii::$app->db->beginTransaction();
            
            try {
                $forms->user->setAuthKey();
                
                if (!$forms->user->save(false)) {
                    throw new \Exception('Could not save user');
                }
                
                $forms->email->user_id = $forms->user->id;
                $forms->email->setKey();
                
                if (!$forms->email->save(false)) {
                    throw new \Exception('Could not save user email');
                }
                
                $forms->password->user_id = $forms->user->id;
                $forms->password->setPassword($forms->password->password);
                
                if (!$forms->password->save(false)) {
                    throw new \Exception('Could not save user password');
                }
                
                $transaction->commit();
                
                Yii::$app->session->addFlash('success', Yii::t('app', 'New user has been created'));
				
                return $this->refresh();
                
            } catch (\Exception $e) {
                $transaction->rollBack();exit;
            }
        }
        
        return $this->render('create', compact('forms'));
    }
    
    public function actionEdit($id)
    {
        $user = User::findOne($id);
        
        if (!$user) {
            throw new NotFoundHttpException(Yii::t('app', 'Not found anything'));
        }
        
        if ($user->load(Yii::$app->request->post()) && $user->validate()) {
            if ($user->save(false)) {
                Yii::$app->session->addFlash('success', Yii::t('app', 'User has been edited'));
				
                return $this->refresh();
            }
        } 
        
        return $this->render('edit', compact('user'));
    }
    
    public function actionDelete()
    {
        if (($data = Yii::$app->request->post('data')) && is_array($data)) {
            foreach ($data as $id) {
                User::findOne($id)->delete();
            }
        }
        
        return $this->redirect(Yii::$app->request->referrer);
    }
    
    public function actionVps($id)
    {
        $id = Html::encode($id);
        
        $virtualServers = Vps::find()->where(['user_id' => $id])->orderBy('id DESC');
        
        $dataProvider = new ActiveDataProvider([
            'query' => $virtualServers,
            'pagination' => [
                'pageSize' => 10,
            ],
        ]);
        
        return $this->render('vps', [
            'dataProvider' => $dataProvider,
        ]);
    }
}