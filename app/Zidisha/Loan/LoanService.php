<?php

namespace Zidisha\Loan;

use Propel\Runtime\Connection\ConnectionInterface;

use Propel\Runtime\Propel;
use Zidisha\Analytics\MixpanelService;
use Zidisha\Balance\Map\TransactionTableMap;
use Zidisha\Balance\Transaction;
use Zidisha\Balance\TransactionQuery;
use Zidisha\Borrower\Borrower;
use Zidisha\Currency\CurrencyService;
use Zidisha\Currency\Money;
use Zidisha\Lender\Lender;
use Zidisha\Mail\LenderMailer;

class LoanService
{
    /**
     * @var CurrencyService
     */
    private $currencyService;
    /**
     * @var \Zidisha\Mail\LenderMailer
     */
    private $lenderMailer;
    /**
     * @var MixpanelService
     */
    private $mixpanelService;

    public function __construct(
        CurrencyService $currencyService,
        LenderMailer $lenderMailer,
        MixpanelService $mixpanelService
    ) {
        $this->currencyService = $currencyService;
        $this->lenderMailer = $lenderMailer;
        $this->mixpanelService = $mixpanelService;
    }

    protected $loanIndex;

    public function applyForLoan(Borrower $borrower, $data)
    {
        $data['currencyCode'] = $borrower->getCountry()->getCurrencyCode();

        $loanCategory = CategoryQuery::create()
            ->findOneById($data['categoryId']);

        $data['usdAmount'] = $this->currencyService->convertToUSD(
            Money::create($data['amount'], $data['currencyCode'])
        )->getAmount();

        $loan = Loan::createFromData($data);

        $loan->setCategory($loanCategory);
        $loan->setBorrower($borrower);
        $loan->setStatus(Loan::OPEN);

        $stage = new Stage();
        $stage->setBorrower($loan->getBorrower());
        $stage->setStatus(Loan::OPEN);
        $stage->setStartDate(new \DateTime());
        $loan->addStage($stage);

        $loan->save();

        $this->addToLoanIndex($loan);
    }

    protected function getLoanIndex()
    {
        if ($this->loanIndex) {
            return $this->loanIndex;
        }

        $elasticaClient = new \Elastica\Client();

        $loanIndex = $elasticaClient->getIndex('loans');

        if (!$loanIndex->exists()) {
            $loanIndex->create(
                array(
                    'number_of_shards' => 1,
                    'number_of_replicas' => 1,
                    'analysis' => array(
                        'analyzer' => array(
                            'default_index' => array(
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'filter' => array('lowercase')
                            ),
                            'default_search' => array(
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'filter' => array('standard', 'lowercase')
                            )
                        ),
                    )
                )
            );
        }

        $this->loanIndex = $loanIndex;

        return $loanIndex;
    }

    public function searchLoans($conditions = array(), $page = 1)
    {
        $conditions += ['search' => false];
        $search = $conditions['search'];
        unset($conditions['search']);

        $queryString = new \Elastica\Query\QueryString();

        $loanIndex = $this->getLoanIndex();

        $query = new \Elastica\Query();

        if ($search) {
            $queryString->setDefaultOperator('AND');
            $queryString->setQuery($search);
            $query->setQuery($queryString);
        }

        $filterAnd = new \Elastica\Filter\BoolAnd();
        foreach ($conditions as $field => $value) {
            $termFilter = new \Elastica\Filter\Term();
            $termFilter->setTerm($field, $value);

            $filterAnd->addFilter($termFilter);
        }
        if ($conditions) {
            $query->setFilter($filterAnd);
        }

        $query->setFrom(($page - 1) * 2);
        $query->setSize($page * 2);

        $results = $loanIndex->search($query);

        $ids = [];

        foreach ($results as $result) {
            $data = $result->getData();
            $ids[$data['id']] = $data['id'];
        }

        $loans = LoanQuery::create()->filterById($ids)->find();
        $sortedLoans = $ids;

        foreach ($loans as $loan) {
            $sortedLoans[$loan->getId()] = $loan;
        }
        $sortedLoans = array_filter(
            $sortedLoans,
            function ($l) {
                return !is_scalar($l);
            }
        );

        $paginatorFactory = \App::make('paginator');

        return $paginatorFactory->make(
            $sortedLoans,
            $results->getTotalHits(),
            2
        );
    }

    public function addToLoanIndex(Loan $loan)
    {
        $loanIndex = $this->getLoanIndex();

        $loanType = $loanIndex->getType('loan');

        $data = array(
            'id' => $loan->getId(),
            'category' => $loan->getCategory()->getName(),
            'categoryId' => $loan->getCategory()->getId(),
            'countryId' => $loan->getBorrower()->getCountry()->getId(),
            'country_code' => $loan->getBorrower()->getCountry()->getCountryCode(),
            'summary' => $loan->getSummary(),
            'description' => $loan->getDescription(),
            'status' => $loan->getStatus(),
            'created_at' => $loan->getCreatedAt()->getTimestamp(),
        );

        $loanDocument = new \Elastica\Document($loan->getId(), $data);
        $loanType->addDocument($loanDocument);
        $loanType->getIndex()->refresh();
    }

    public function placeBid(Loan $loan, Lender $lender, $data)
    {
        $con = Propel::getWriteConnection(TransactionTableMap::DATABASE_NAME);
        $con->beginTransaction();

        //TODO: calculate the accepted amount.


        $bidAmount = Money::create($data['amount'], 'USD');
        try {
            $oldBids = BidQuery::create()
                ->getOrderedBids($loan)
                ->find();

            $bid = new Bid();
            $bid
                ->setLoan($loan)
                ->setLender($lender)
                ->setBorrower($loan->getBorrower())
                ->setBidAmount($bidAmount)
                ->setInterestRate($data['interestRate'])
                ->setActive(true)
                ->setBidDate(new \DateTime());

            $bidSuccess = $bid->save($con);
            if (!$bidSuccess) {
                throw new \Exception();
            }

            $newBids = BidQuery::create()
                ->getOrderedBids($loan)
                ->find();

            $oldAcceptedBids = $this->getAcceptedBids($oldBids, $loan->getAmount());
            $newAcceptedBids = $this->getAcceptedBids($newBids, $loan->getAmount());
            $changedBids = $this->getChangedBids($oldAcceptedBids, $newAcceptedBids);

            foreach ($changedBids as $bidId => $changedBid) {
                $bidTransaction = new Transaction();
                $bidTransaction
                    ->setUser($changedBid['bid']->getLender()->getUser())
                    ->setAmount($changedBid['acceptedAmount'])
                    ->setDescription($changedBid['description'])
                    ->setLoan($changedBid['bid']->getLoan())
                    ->setTransactionDate(new \DateTime())
                    ->setType($changedBid['type'])
                    ->setSubType($changedBid['subType']);

                $bidTransactionSuccess = $bidTransaction->save($con);
                if (!$bidTransactionSuccess) {
                    // Todo: Notify admin.
                    throw new \Exception();
                }
            }

            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();
            throw $e;
        }

        // loop and send emails

        // Send bid confirmation mail
        $this->lenderMailer->bidPlaceMail($bid);

        if ($bid->isFirstBid()) {
            $this->lenderMailer->sendPlaceBidMail($bid);
        }

        $this->mixpanelService->trackPlacedBid($bid);

        $totalBidAmount = BidQuery::create()
            ->filterByLoan($loan)
            ->getTotalBidAmount();

        if ($totalBidAmount->compare($loan->getAmount()) != -1) {

            $bids = BidQuery::create()
                ->filterByLoan($loan)
                ->find();

            foreach ($bids as $oneBid) {
                $this->lenderMailer->loanCompletionMail($oneBid);
            }
        }

        //Todo: Lender Invite Credit.

        return $bid;
    }

    protected function getAcceptedBids($bids, Money $loanAmount)
    {
        $zero = Money::create(0, 'USD');
        $totalBidAmount = $zero;
        $acceptedBids = [];

        foreach ($bids as $bid) {
            $bidAmount = $bid->getBidAmount();
            $missingAmount = $loanAmount->subtract($totalBidAmount)->max($zero)->round(3);
            $totalBidAmount = $totalBidAmount->add($bidAmount);
            $acceptedAmount = $missingAmount->min($bidAmount);

            $acceptedBids[$bid->getId()] = compact('bid', 'acceptedAmount');
        }

        // Sort by bid date
        // TODO: why?
        uasort(
            $acceptedBids,
            function ($b1, $b2) {
                return $b1['bid']->getBidDate() <= $b2['bid']->getBidDate();
            }
        );

        return $acceptedBids;
    }

    protected function getChangedBids($oldAcceptedBids, $newAcceptedBids)
    {
        $changedBids = [];

        foreach ($newAcceptedBids as $bidId => $acceptedBid) {
            $acceptedAmount = $acceptedBid['acceptedAmount'];
            $bid = $acceptedBid['bid'];
            if (isset($oldAcceptedBids[$bidId])) {
                $oldAcceptedAmount = $oldAcceptedBids[$bidId]['acceptedAmount'];
                if ($oldAcceptedAmount->greaterThan($acceptedAmount)) {
                    $changedBids[$bidId] = [
                        'bid' => $bid,
                        'acceptedAmount' => $acceptedAmount,
                        'type' => Transaction::LOAN_OUTBID,
                        'subType' => null,
                        'description' => 'Loan outbid',
                        'changedAmount' => $oldAcceptedAmount->substract($acceptedAmount),
                    ];
                } else {
                    if ($oldAcceptedAmount->lessThan($acceptedAmount)) {
                        $changedBids[$bidId] = [
                            'bid' => $bid,
                            'acceptedAmount' => $acceptedAmount,
                            'type' => Transaction::LOAN_BID,
                            'subType' => Transaction::UPDATE_BID,
                            'description' => 'Loan bid',
                            'changedAmount' => $acceptedAmount->substract($oldAcceptedAmount),
                        ];

                    }
                }
            } else {
                $changedBids[$bidId] = [
                    'bid' => $bid,
                    'acceptedAmount' => $acceptedAmount,
                    'type' => Transaction::LOAN_BID,
                    'subType' => Transaction::PLACE_BID,
                    'description' => 'Loan bid',
                    'changedAmount' => $acceptedAmount,
                ];

            }
        }

        return $changedBids;
    }

    public function editBid(Bid $bid, $data)
    {
        // Todo: Outbid Function

        $con = Propel::getWriteConnection(TransactionTableMap::DATABASE_NAME);
        $con->beginTransaction();
        try {

            $bid->setBidAmount(Money::create($data['amount'], 'USD'));
            $bid->setInterestRate($data['interestRate']);
            $bidEditSuccess = $bid->save($con);

            if ($bidEditSuccess) {
                $con->commit();
            }
        } catch (\Exception $e) {
            $con->rollBack();
        }

        return $bid;
    }

    public function acceptBids(Loan $loan)
    {
        $newBids = BidQuery::create()
            ->getOrderedBids($loan)
            ->find();

        $newAcceptedBids = $this->getAcceptedBids($newBids, $loan->getAmount());

        $con = Propel::getWriteConnection(TransactionTableMap::DATABASE_NAME);
        $con->beginTransaction();
        $totalAmount = Money::create(0);

        try {
            foreach ($newAcceptedBids as $bidId => $acceptedBid) {
                $acceptedAmount = $acceptedBid['acceptedAmount'];
                $bid = $acceptedBid['bid'];
                if ($acceptedAmount->greaterThan(Money::create(0))) {
                    $bid->setActive(0)
                        ->setAcceptedAmount($acceptedAmount);
                    $success = $bid->save($con);
                    if (!$success) {
                        // Todo: Notify admin.
                        throw new \Exception();
                    }
                    $totalAmount = $totalAmount->add($acceptedAmount->multiply(0.01 * $bid->getInterestRate()));
                }
            }

            $totalInterest = $totalAmount->divide($loan->getUsdAmount())->round(2)->getAmount();
            $loan->setStatus(Loan::FUNDED)
                ->setInterestRate($totalInterest)
                ->setAcceptedDate(new \DateTime())
                ->save($con);

            $this->changeLoanStage($con, $loan, Loan::OPEN, Loan::FUNDED);

            $loan->getBorrower()->setActiveLoan($loan);
            $loan->save($con);

            //TODO send emails

        } catch (\Exception $e) {
            $con->rollBack();
        }
        $con->commit();

        return true;
    }

    public function expireLoan(Loan $loan)
    {
        $con = Propel::getWriteConnection(TransactionTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $loan->setStatus(Loan::EXPIRED)
                ->setExpiredDate(new \DateTime());
            $setLoanSuccess = $loan->save($con);

            $loan->getBorrower()
                ->setActiveLoan(null)
                ->setLoanStatus(Loan::NO_LOAN);
            $setBorrowerSuccess = $loan->save($con);

            $this->changeLoanStage($con, $loan, Loan::OPEN, Loan::EXPIRED);

            $refunds = $this->refundLenders($con, $loan, Loan::EXPIRED);

            $updateBidsSuccess = true;
            if ($loan->getStatus() == Loan::FUNDED) {
                $updateBidsSuccess = BidQuery::create()
                    ->filterByLoan($loan)
                    ->update(['active' => 0, 'accepted_amount' => null], $con);
            }

            if (!$updateBidsSuccess || !$setBorrowerSuccess || !$setLoanSuccess) {
                throw new \Exception();
            }
            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();
        }

        // Todo: send emails to notify lenders use $refunds
        return true;
    }

    protected function refundLenders(ConnectionInterface $con, Loan $loan, $status = Loan::EXPIRED)
    {

        $transactionType = Transaction::LOAN_OUTBID;
        $transactionSubType = Transaction::LOAN_BID_EXPIRED;
        $description = 'Loan bid expired';

        if ($status == Loan::CANCELED) {
            $transactionSubType = Transaction::LOAN_BID_CANCELED;
            $description = 'Loan bid cancelled';
        }

        $transactions = TransactionQuery::create()
            ->filterByLoan($loan)
            ->filterByType([Transaction::LOAN_BID, Transaction::LOAN_OUTBID])
            ->find();

        $refunds = [];
        foreach ($transactions as $transaction) {
            $refunds[$transaction->getUserId()] = [
                'lender' => $transaction->getUser()->getLender(),
                'refundAmount' => array_get($refunds, 'id.refundAmount', Money::create(0))->add(
                        $transaction->getAmount()->multiply(-1)
                    ),
            ];
        }

        foreach ($refunds as $refund) {
            if (!$refund['refundAmount']->greaterThan(Money::create(0))) {
                continue;
            }

            $refundTransaction = new Transaction();
            $refundTransaction
                ->setAmount($refund['refundAmount'])
                ->setUser($refund['lender']->getUser())
                ->setLoan($loan)
                ->setType($transactionType)
                ->setSubType($transactionSubType)
                ->setDescription($description);

            $refundTransaction->save($con);
        }
        // TODO: lender invite

        return $refunds;
    }

    private function changeLoanStage(
        ConnectionInterface $con,
        Loan $loan,
        $oldStatus,
        $newStatus,
        \DateTime $date = null
    ) {
        $date = $date ? : new \DateTime();

        $currentLoanStage = StageQuery::create()
            ->filterByLoan($loan)
            ->findOneByStatus($oldStatus);

        $currentLoanStage->setEndDate($date);
        $currentLoanStageSuccess = $currentLoanStage->save($con);

        $newLoanStage = new Stage();
        $newLoanStage->setLoan($loan)
            ->setBorrower($loan->getBorrower())
            ->setStatus($newStatus)
            ->setStartDate($date);

        $newLoanStageSuccess = $newLoanStage->save($con);

        if (!$currentLoanStageSuccess || !$newLoanStageSuccess) {
            throw new \Exception();
        }
    }

    public
    function disburseLoan(
        Loan $loan,
        \DateTime $date,
        Money $amount
    ) {
        $isDisbursed = TransactionQuery::create()
            ->filterByLoan($loan)
            ->filterByType(Transaction::DISBURSEMENT)
            ->find();

        if ($isDisbursed) {
            // TODO
            return;
        }

        $con = Propel::getWriteConnection(TransactionTableMap::DATABASE_NAME);
        $con->beginTransaction();
        try {
            $disburseTransaction = new Transaction();
            $disburseTransaction
                ->setUser($loan->getBorrower())
                ->setAmount(-$amount)
                ->setDescription('Got amount from loan')
                ->setLoan($loan)
                ->setTransactionDate(new \DateTime())
                ->setType(Transaction::DISBURSEMENT);
            $TransactionSuccess = $disburseTransaction->save($con);

            $TransactionSuccess_2 = $TransactionSuccess_3 = true;
            $loans = LoanQuery::create()
                ->filterByBorrower($loan->getBorrower())
                ->count(); // propel count()
            if ($loans == 1) {
                $feeTransactionBorrower = new Transaction();
                $feeTransactionBorrower
                    ->setUser($loan->getBorrower()->getUser())
                    ->setAmount(-($amount->multiply(2.5)))
                    ->setDescription('Registration Fee')
                    ->setLoan($loan)
                    ->setTransactionDate(new \DateTime())
                    ->setType(Transaction::REGISTRATION_FEE);
                $TransactionSuccess_2 = $feeTransactionBorrower->save($con);

                $feeTransactionAdmin = new Transaction();
                $feeTransactionAdmin
                    ->setUserId(\Config::get('adminId'))
                    ->setAmount($amount->multiply(2.5))
                    ->setDescription('Registration Fee')
                    ->setLoan($loan)
                    ->setTransactionDate(new \DateTime())
                    ->setType(Transaction::REGISTRATION_FEE);
                $TransactionSuccess_3 = $feeTransactionAdmin->save($con);
            }

            $loan->setStatus(Loan::ACTIVE)
                ->setDisbursedAmount($amount);
            $loan->save($con);

            $currentLoanStage = StageQuery::create()
                ->filterByLoan($loan)
                ->findOneByStatus(Loan::FUNDED);
            $currentLoanStage->setEndDate(new \DateTime())
                ->save($con);

            $newLoanStage = new Stage();
            $newLoanStage->setLoan($loan);
            $newLoanStage->setBorrower($loan->getBorrower());
            $newLoanStage->setStatus(Loan::ACTIVE);
            $newLoanStage->setStartDate(new \DateTime());
            $newLoanStage->save($con);

            if (!$TransactionSuccess && !$TransactionSuccess_2 && !$TransactionSuccess_3) {
                throw new \Exception();
            }
        } catch (\Exception $e) {
            $con->rollBack();
        }
        $con->commit();
        //TODO Add repayment schedule
        //TODO Send email / sift sience event
    }
}


