<?php

use Zidisha\Borrower\Form\EditProfile;
use Zidisha\Borrower\BorrowerQuery;
use Zidisha\Borrower\ProfileQuery;

class BorrowerController extends BaseController
{

    private $editProfileForm;

    public function __construct(EditProfile $editProfileForm)
    {
        $this->editProfileForm = $editProfileForm;
    }

    public function getPublicProfile($username)
    {
        $borrower = BorrowerQuery::create()
            ->useUserQuery()
            ->filterByUsername($username)
            ->endUse()
            ->findOne();

        if(!$borrower){
            App::abort(404);
        }

        return View::make(
            'borrower.public-profile',
            compact('borrower')
        );
    }

    public function getEditProfile()
    {
        return View::make(
            'borrower.edit-profile',
            ['form' => $this->editProfileForm,]
        );
    }

    public function postEditProfile()
    {
        $form = $this->editProfileForm;
        $form->handleRequest(Request::instance());

        if ($form->isValid()) {
            $data = $form->getData();

            $borrower = \Auth::user()->getBorrower();

            $borrower->setFirstName($data['firstName']);
            $borrower->setLastName($data['lastName']);
            $borrower->getUser()->setEmail($data['email']);
            $borrower->getUser()->setUsername($data['username']);
            $borrower->getProfile()->setAboutMe($data['aboutMe']);
            $borrower->getProfile()->setAboutBusiness($data['aboutBusiness']);

            if (!empty($data['password'])) {
                $borrower->getUser()->setPassword($data['password']);
            }

            $borrower->save();

            if(Input::hasFile('picture'))
            {
                $image = Input::file('picture');
                $image->move(public_path() . '/images/profile/', $data['username'].'.jpg' );
            }

            return Redirect::route('borrower:public-profile' , $data['username']);
        }

        return Redirect::route('borrower:edit-profile')->withForm($form);
    }

    public function getDashboard(){
        return View::make('borrower.dashboard');
    }
}
