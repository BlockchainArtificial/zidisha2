<?php

use Zidisha\Lender\Lender;
use Zidisha\User\User;
use Zidisha\User\UserQuery;
use Zidisha\User\UserService;
use Zidisha\Vendor\Facebook\FacebookService;

class AuthController extends BaseController
{

    /**
     * @var Zidisha\Vendor\Facebook\FacebookService
     */
    private $facebookService;
    
    /**
     * @var Zidisha\User\UserService
     */
    private $userService;

    public function __construct(FacebookService $facebookService, UserService $userService)
    {
        $this->facebookService = $facebookService;
        $this->userService = $userService;
    }
    
    public function getJoin()
    {
       return View::make('auth.join', [
            'facebookJoinUrl' => $this->facebookService->getLoginUrl('facebook:join'),
        ]);
    }

    public function postJoin()
    {
        $validator = $this->userService->getJoinValidator(Input::all());

        if ($validator->fails()) {
            return Redirect::to('join')->withInput()->withErrors($validator);
        }

        $user = $this->userService->joinUser(Input::all());

        if ($user) {
            Auth::login($user);
            return Redirect::route('home');
        }

        Flash::error('Oops, something went wrong');
        return Redirect::to('join')->withInput();
    }

    public function getLogin()
    {
        return View::make('auth.login', [
            'facebookLoginUrl' => $this->facebookService->getLoginUrl('facebook:login'),
        ]);
    }

    public function postLogin()
    {
        $rememberMe = Input::has('remember_me');
        $credentials = Input::only('username', 'password');
        
        if (Auth::attempt($credentials, $rememberMe)) {
            return Redirect::route('home');
        }

        Flash::error("Wrong username or password!");
        return Redirect::route('login');
    }

    public function getLogout()
    {
        Auth::logout();
        $this->facebookService->logout();
        return Redirect::route('home');
    }

    public function getFacebookJoin()
    {
        $facebookUser = $this->getFacebookUser();

        if (Session::has('error')) {
            return View::make('auth.join', [
                'facebookJoinUrl' => $this->facebookService->getLoginUrl('facebook:join'),
            ]);
        }
        if ($facebookUser) {
            return View::make('auth.confirm');
        }

        return Redirect::to('join');
    }

    public function postFacebookConfirm()
    {
        $facebookUser = $this->getFacebookUser();

        if ($facebookUser) {
            $rules = [
                'username' => 'required|max:20'
            ];

            $validator = Validator::make(Input::all(), $rules);

            if ($validator->fails()) {
                return Redirect::route('facebook:join')->withErrors($validator);
            }

            $user = new User();
            $user
                ->setUsername(Input::get('username'))
                ->setEmail($facebookUser['email'])
                ->setFacebookId($facebookUser['id']);

            $lender = new Lender();
            $lender
                ->setUser($user)
                ->setFirstName($facebookUser['first_name'])
                ->setLastName($facebookUser['last_name'])
                ->setAboutMe(Input::get('about_me'))
                // TODO
                //->setCountry($facebookUser['location']);
                ->setCountryId(1);

            $lender->save();

            Auth::loginUsingId($user->getId());

            return Redirect::to('login')->with('success', 'You have successfully joined Zidisha.');
        } else {
            return Redirect::to('join')->with('error', 'No Facebook account connected.');
        }
    }

    private function getFacebookUser()
    {
        $facebookUser = $this->facebookService->getUserProfile();
        
        if ($facebookUser) {
            $errors = $this->userService->validateConnectingFacebookUser($facebookUser);

            if ($errors) {
                foreach ($errors as $error) {
                    Flash::error($error);
                }
                return Redirect::to('join');
            }

            return $facebookUser;
        }
        
        return false;
    }

    public function getFacebookLogin()
    {
        $facebookUserId = $this->facebookService->getUserId();

        if ($facebookUserId) {
            $checkUser = UserQuery::create()
                ->filterByFacebookId($facebookUserId)
                ->findOne();

            if ($checkUser) {
                Auth::loginUsingId($checkUser->getId());
            } else {
                return Redirect::to('login')->with(
                    'error',
                    'You are not registered to use Facebook. Please sign up with Facebook first.'
                );
            }

            return Redirect::route('home');
        } else {
            return Redirect::to('login');
        }
    }
}
