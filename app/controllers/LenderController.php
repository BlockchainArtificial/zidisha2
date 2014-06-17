<?php

use Illuminate\Support\Facades\View;
use Zidisha\Balance\TransactionQuery;
use Zidisha\Lender\Form\EditProfile;
use Zidisha\Lender\Form\Funds;
use Zidisha\Lender\LenderQuery;
use Zidisha\Lender\ProfileQuery;

class LenderController extends BaseController
{
    protected $transactionQuery;

    /**
     * @var Zidisha\Lender\Form\EditProfile
     */
    private $editProfileForm, $fundsForm;

    public function __construct(EditProfile $editProfileForm, TransactionQuery $transactionQuery, Funds $fundsForm)
    {
        $this->editProfileForm = $editProfileForm;
        $this->transactionQuery = $transactionQuery;
        $this->fundsForm = $fundsForm;
    }
    
    public function getPublicProfile($username)
    {


        $lender = LenderQuery::create()
            ->useUserQuery()
                ->filterByUsername($username)
            ->endUse()
            ->findOne();

        if(!$lender){
            \Illuminate\Support\Facades\App::abort(404);
        }
        return View::make(
            'lender.public-profile',
            compact('lender')
        );
    }

    public function getEditProfile()
    {
        return View::make(
            'lender.edit-profile',
            ['form' => $this->editProfileForm,]
        );
    }

    public function postEditProfile()
    {
        $form = $this->editProfileForm;
        $form->handleRequest(Request::instance());
        
        if ($form->isValid()) {
            $data = $form->getData();

            $lender = Auth::user()->getLender();

            $lender->setFirstName($data['firstName']);
            $lender->setLastName($data['lastName']);
            $lender->getUser()->setEmail($data['email']);
            $lender->getUser()->setUsername($data['username']);
            $lender->getProfile()->setAboutMe($data['aboutMe']);

            if (!empty($data['password'])) {
                $lender->getUser()->setPassword($data['password']);
            }

            $lender->save();

            if(Input::hasFile('picture'))
            {
                $image = Input::file('picture');
                $image->move(public_path() . '/images/profile/', $data['username'].'.jpg' );
            }

            return Redirect::route('lender:public-profile', $data['username']);
        }

        return Redirect::route('lender:edit-profile')->withForm($form);
    }

    public function getDashboard(){
        return View::make('lender.dashboard');
    }

    public function getTransactionHistory(){

        $currentBalance = $this->transactionQuery
            ->select(array('total'))
            ->withColumn('SUM(amount)', 'total')
            ->filterByUserId(Auth::getUser()->getId())
            ->findOne();

        $page = Request::query('page') ?: 1;

        $currentBalancePageObj = DB::select(
            'SELECT SUM(amount) AS total
             FROM transactions
             WHERE id IN (SELECT id
                          FROM transactions WHERE user_id = ?
                          ORDER BY transaction_date DESC, transactions.id DESC
                          OFFSET ?)',
             array(Auth::getUser()->getId(), ($page-1) * 50));

        $currentBalancePage = $currentBalancePageObj[0]->total;

        $paginator = $this->transactionQuery->create()
            ->orderByTransactionDate('desc')
            ->orderById('desc')
            ->filterByUserId(Auth::getUser()->getId())
            ->paginate($page, 50);

        return View::make('lender.history', compact('paginator', 'currentBalance', 'currentBalancePage'));
    }

    public function getFunds(){

        $currentBalance = $this->transactionQuery
            ->select(array('total'))
            ->withColumn('SUM(amount)', 'total')
            ->filterByUserId(Auth::getUser()->getId())
            ->findOne();

        return View::make('lender.funds', compact('currentBalance'), ['form' => $this->fundsForm,]);
    }
}
