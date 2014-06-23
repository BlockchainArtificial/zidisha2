<?php

use Zidisha\Currency\Money;
use Zidisha\Loan\Bid;

class LoanServiceCest
{
    /**
     * @var Zidisha\Loan\LoanService
     */
    private $loanService;

    public function _before(UnitTester $I)
    {
        $this->loanService = $I->grabService('Zidisha\Loan\LoanService');
    }

    public function _after(UnitTester $I)
    {
    }

    public function testPlaceBid(UnitTester $I)
    {
        $loan = \Zidisha\Loan\LoanQuery::create()
            ->findOneById('1');

        $lender = \Zidisha\Lender\LenderQuery::create()
            ->findOneById('32');

        $data = [
            'amount' => '10',
            'interestRate' => '5'
        ];

//        $this->loanService->placeBid($loan, $lender, $data);

    }

    public function testGetAcceptedBids(UnitTester $I)
    {
        // id => ['interestRate', 'bidAmount', 'acceptedAmount']

        $this->verifyAcceptedBids(
            [
                '1' => ['3', '50', '50'],
                '20' => ['4', '20', '20'],
                '8' => ['10', '100', '100'],
            ],
            200
        );

        $this->verifyAcceptedBids(
            [
                '8' => ['1', '23', '23'],
                '1' => ['3', '50', '50'],
                '20' => ['4', '20', '20'],
                '27' => ['5', '34', '34'],
                '45' => ['6', '34', '34'],
                '65' => ['8', '75', '39'],
                '55' => ['9', '55', '0'],
                '88' => ['11', '95', '0'],
                '98' => ['15', '85', '0'],
            ],
            200
        );

        $this->verifyAcceptedBids(
            [
                '1' => ['3', '10', '10'],
            ],
            120
        );
    }

    private function generateBid(array $bidData)
    {
        $bids = [];

        foreach ($bidData as $id => $bid) {
            $newBid = new Bid();
            $newBid->setInterestRate($bid[0]);
            $newBid->setBidAmount(Money::create($bid[1]));
            $newBid->setBidDate(new \DateTime());
            $newBid->setId($id);
            $bids[$id] = $newBid;
        }

        return $bids;
    }

    /**
     * @param $bidData
     * @param $amount
     */
    protected function verifyAcceptedBids($bidData, $amount)
    {
        $method = new ReflectionMethod($this->loanService, 'getAcceptedBids');
        $method->setAccessible(true);

        $bids = $this->generateBid($bidData);

        $acceptedBids = $method->invoke($this->loanService, $bids, Money::create($amount));

        foreach ($bidData as $id => $data) {
            verify($acceptedBids)->hasKey($id);
            verify($acceptedBids[$id]['acceptedAmount'])->equals(Money::create($data[2]));
        }
    }

    public function testApplyForLoan(UnitTester $I)
    {
        $borrower = \Zidisha\Borrower\BorrowerQuery::create()
            ->findOneById(12);

        $borrowerId = $borrower->getId();
        $data = [
            'categoryId' => '7',
            'nativeAmount' => '798097',
            'summary' => 'suasdasd',
            'description' => 'asdasda',
            'installmentAmount' => '2312',
            'installmentDay' => '1',
        ];

        $this->loanService->applyForLoan($borrower, $data);
        $I->seeInDatabase('loans', ['status' => '0', 'borrower_id' => $borrowerId]);
    }
} 