<?php

class LendController extends BaseController
{

    protected $loanCategoryQuery;
    protected $countryQuery;
    protected $loanQuery;

    public function  __construct(
        Zidisha\Loan\CategoryQuery $loanCategoryQuery,
        Zidisha\Country\CountryQuery $countryQuery,
        Zidisha\Loan\LoanQuery $loanQuery
    ) {
        $this->loanCategoryQuery = $loanCategoryQuery;
        $this->countryQuery = $countryQuery;
        $this->loanQuery = $loanQuery;
    }

    public function getIndex($category = null, $country = null)
    {
        // for categories
        $loanCategories = $this->loanCategoryQuery
            ->orderByRank()
            ->find();

        //for countries
        $countries = $this->countryQuery
            ->orderByName()
            ->find();

        //for loans
        $loanQuery = $this->loanQuery->orderBySummary();

        $loanCategoryName = $category;
        $selectedLoanCategory = $this->loanCategoryQuery
            ->findOneBySlug($loanCategoryName);

        $routeParams = ['category' => 'all'];

        if ($selectedLoanCategory) {
            $loanQuery->filterByLoanCategoryId($selectedLoanCategory->getId());
            $routeParams['category'] = $selectedLoanCategory->getSlug();
        }

        $countryName = $country;
        $selectedCountry = $this->countryQuery->findOneBySlug($countryName);

        if($selectedCountry){
            $loanQuery
                ->useBorrowerQuery()
                    ->filterByCountryId($selectedCountry->getId())
                ->endUse();
            $routeParams['country'] = $selectedCountry->getSlug();
        }

        $page = Request::query('page') ?: 1;
        $paginator = $this->loanQuery->paginate($page, 2);

        return View::make(
            'pages.lend',
            compact('countries', 'selectedCountry', 'loanCategories', 'selectedLoanCategory', 'paginator', 'routeParams')
        );

    }
}