<?php

namespace Eva\EvaUser\Controllers;

use Eva\EvaOAuthClient\Models\OAuthManager;
use Eva\EvaUser\Models;
use Eva\EvaUser\Forms;

class RegisterController extends ControllerBase
{
    public function indexAction()
    {
        if (!$this->request->isPost()) {
            return;
        }

        if ($this->request->isAjax() || $this->request->get('ajax')) {
            $form = new Forms\RegisterForm();
            if ($form->isValid($this->request->getPost()) === false) {
                return $this->showInvalidMessagesAsJson($form);
            }
            $user = new Models\Register();
            $user->assign(array(
                'username' => $this->request->getPost('username'),
                'email' => $this->request->getPost('email'),
                'password' => $this->request->getPost('password'),
                'usernameCustomized' => 1,
                'providerType' => $user->getProviderType('web', 'manual', 'email'),
                'source' => Models\LoginRecord::getSourceOfUser(),
            ));
            try {
                $registerUser = $user->register();

                return $this->showResponseAsJson($registerUser);
            } catch (\Exception $e) {
                return $this->showExceptionAsJson($e, $user->getMessages());
            }
        } else {
            $form = new Forms\RegisterForm();
            $registerSuccessRedirectUri = $this->dispatcher->getParam('registerSuccessRedirectUri');
            $registerSuccessRedirectUri = $registerSuccessRedirectUri ? $registerSuccessRedirectUri : $this->getDI()->getConfig()->user->registerSuccessRedirectUri;

            $registerFailedRedirectUri = $this->dispatcher->getParam('registerFailedRedirectUri');
            $registerFailedRedirectUri = $registerFailedRedirectUri ? $registerFailedRedirectUri : $this->getDI()->getConfig()->user->registerFailedRedirectUri;
            $registerFailedRedirectUri = $registerFailedRedirectUri ? $registerFailedRedirectUri : $this->request->getURI();
            if ($form->isValid($this->request->getPost()) === false) {
                $this->showInvalidMessages($form);

                return $this->redirectHandler($registerFailedRedirectUri, 'error');
            }
            $user = new Models\Register();
            $user->assign(array(
                'username' => $this->request->getPost('username'),
                'email' => $this->request->getPost('email'),
                'password' => $this->request->getPost('password'),
                'usernameCustomized' => 1,
                'providerType' => $user->getProviderType('web', 'manual', 'email'),
                'source' => Models\LoginRecord::getSourceOfUser(),
            ));

            try {
                $user->register();
            } catch (\Exception $e) {
                $this->showException($e, $user->getMessages());

                return $this->redirectHandler($registerFailedRedirectUri, 'error');
            }
            $this->flashSession->success('SUCCESS_USER_REGISTERED_ACTIVE_MAIL_SENT');

            return $this->redirectHandler($registerSuccessRedirectUri);
        }
    }

    public function mobileAction()
    {
        if (!$this->request->isPost()) {
            return;
        }

        $data = $this->request->getPost();
        //配资姓名不需要username 和 email ，但是去掉对原有的登录系统又风险，所以先做个假的
//        $data['username'] = 'wscn_mobile_'.$data['mobile'];
//        $randomNumber = chr(mt_rand(97, 122));
//        $data['username'] = $data['mobile'] . '_' . $randomNumber;
        $data['username'] = 'mobi_' . uniqid() . substr($data['mobile'], 7);
        $data['email'] = $data['mobile'] . '@fake.wallstreetcn.com';
//        $data['screenName'] =
//            substr($data['mobile'], 0, 3) . '******' . substr($data['mobile'], -2) . '_' . $randomNumber;

        if ($this->request->isAjax() || $this->request->get('ajax')) {
            $form = new Forms\MobileRegisterForm();
            if ($form->isValid($data) === false) {
                return $this->showInvalidMessagesAsJson($form);
            }
            $user = new Models\Register();
            $user->assign(array(
                'email' => $data['email'],
                'username' => $data['username'],
                'mobile' => $this->request->getPost('mobile'),
                'password' => $this->request->getPost('password'),
                'screenName' => $data['screenName'],
                'usernameCustomized' => 0,
                'providerType' => $user->getProviderType('web', 'manual', 'mobile'),
                'source' => Models\LoginRecord::getSourceOfUser(),
            ));

            $captcha = $this->request->getPost('captcha');
            try {
                $registerUser = $user->registerByMobile($captcha);

                return $this->showResponseAsJson($registerUser);
            } catch (\Exception $e) {
                return $this->showExceptionAsJson($e, $user->getMessages());
            }
        } else {
            $form = new Forms\MobileRegisterForm();
            if ($form->isValid($data) === false) {
                $this->showInvalidMessages($form);

                return $this->redirectHandler($this->getDI()->getConfig()->user->registerFailedRedirectUri, 'error');
            }
            $user = new Models\Register();
            $user->assign(array(
                'username' => $data['username'],
                'mobile' => $this->request->getPost('mobile'),
                'password' => $this->request->getPost('password'),
                'providerType' => $user->getProviderType('web', 'manual', 'mobile'),
                'source' => Models\LoginRecord::getSourceOfUser(),
            ));

            $captcha = $this->request->getPost('captcha');
            try {
                $user->registerByMobile($captcha);
            } catch (\Exception $e) {
                $this->showException($e, $user->getMessages());

                return $this->redirectHandler($this->getDI()->getConfig()->user->registerFailedRedirectUri, 'error');
            }
            $this->flashSession->success('SUCCESS_USER_REGISTERED_ACTIVE_MAIL_SENT');

            return $this->redirectHandler($this->getDI()->getConfig()->user->registerSuccessRedirectUri);
        }
    }


    public function mobileCaptchaAction()
    {
        $mobile = $this->request->getPost('mobile');
        if($this->request->get('type', 'string') == 'xgb'){ //如果get过来xgb,则是选股宝找回密码的短信
            $type = Models\Register::XGB_RESET_CODE;
        }else{
            $type = Models\Register::REGISTER_CODE;
        }
         //短信的类型
        $registerModel = new Models\Register();
        $result = $registerModel->mobileCaptcha($mobile, $type);

        $data = array('mobile' => $mobile, 'timestamp' => time());

        return $this->showResponseAsJson($data);
    }


    public function mobileCaptchaCheckAction()
    {
        $mobile = $this->request->getPost('mobile');
        $captcha = $this->request->getPost('captcha');

        $registerModel = new Models\Register();
        $result = $registerModel->mobileCaptchaCheck($mobile, $captcha);

        $data = array('mobile' => $mobile, 'result' => $result);

        return $this->showResponseAsJson($data);
    }

    public function checkAction()
    {
        $username = $this->request->get('username');
        $email = $this->request->get('email');
        $mobile = $this->request->get('mobile');

        if ($this->hasQQ($username)) {
            $this->response->setStatusCode('409', 'User Already Exists');
        }

        $loginedUser = Models\Login::getCurrentUser();
        $extraCondition = '';
        // 已登录用户表示当前为修改用户名，允许与当前用户名相同
        if ($loginedUser['id'] > 0) {
            $extraCondition .= ' AND id != ' . $loginedUser['id'];
        }
        if ($username) {
            $userinfo = Models\Login::findFirst(array("username = '$username' {$extraCondition}"));
        } elseif ($email) {
            $userinfo = Models\Login::findFirst(array("email = '$email' {$extraCondition}"));
        } elseif ($mobile) {
            $userinfo = Models\Login::findFirst(array("mobile = '$mobile' {$extraCondition}"));
        } else {
            $userinfo = array();
        }
        $this->view->disable();
        if ($userinfo) {
            $this->response->setStatusCode('409', 'User Already Exists');
        }

        return $this->response->setJsonContent(array(
            'exist' => $userinfo ? true : false,
            'id' => $userinfo ? $userinfo->id : 0,
            'status' => $userinfo ? $userinfo->status : null,
        ));
    }

    /**
     * 禁止用户名中含QQ号
     * @param $username
     * @return bool
     */
    public function hasQQ($username)
    {
        if ($username) {
            $pos = stripos($username, 'q');
            if ($pos !== false) {
                $num = preg_match_all('/\d/', $username);
                if ($num > 6) {
                    return true;
                }
            }
        }

        return false;
    }
}
