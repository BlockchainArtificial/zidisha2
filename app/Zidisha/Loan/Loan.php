<?php

namespace Zidisha\Loan;

use Zidisha\Currency\Money;
use Zidisha\Loan\Base\Loan as BaseLoan;

class Loan extends BaseLoan
{

    const OPEN = 0;
    const FUNDED = 1;
    const ACTIVE = 2;
    const REPAID = 3;
    const NO_LOAN = 4;
    const DEFAULTED = 5;
    const CANCELED = 6;
    const EXPIRED = 7;
    
    const WEEKLY_INSTALLMENT  = 'week';
    const MONTHLY_INSTALLMENT = 'month';

    public static function createFromData($data)
    {
        $currency = $data['currencyCode'];

        $loan = new Loan();
        $loan->setSummary($data['summary']);
        $loan->setDescription($data['description']);

        $loan->setCurrencyCode($data['currencyCode']);
        $loan->setNativeAmount(Money::create($data['nativeAmount'], $currency));

        $loan->setAmount(Money::create($data['amount'], 'USD'));
        $loan->setRegistrationFeeRate('5');
        $loan->setInstallmentPeriod('monthly');
        $loan->setInterestRate($data['interestRate']);
        $loan->setAmountRaised($data['amountRaised']);

        $loan->setInstallmentDay($data['installmentDay']);
        $loan->setApplicationDate(new \DateTime());
        $loan->calculateInstallmentCount(Money::create($data['installmentAmount'], $currency));

        return $loan;
    }

    public function getNativeAmount()
    {
        return Money::create(parent::getnativeAmount(), $this->getCurrencyCode());
    }

    public function setNativeAmount($money)
    {
        return parent::setNativeamount($money->getAmount());
    }

    public function getInstallmentAmount()
    {
        return Money::create(parent::getInstallmentAmount(), $this->getCurrencyCode());
    }

    public function setInstallmentAmount($money)
    {
        return parent::setInstallmentAmount($money->getAmount());
    }

    public function getAmount()
    {
        return Money::create(parent::getAmount(), 'USD');
    }

    public function setAmount($money)
    {
        return parent::setAmount($money->getAmount());
    }

    public function calculateExtraDays($disbursedDate)
    {
        $installmentDay = $this->getInstallmentDay();
        $day = $disbursedDate->format('d');

        if ($day <= $installmentDay) {
            $diff = $installmentDay - $day;
        } else {
            $next = date("Y-m-$installmentDay", strtotime("+1 months"));
            $diff = date('d', strtotime($next) - strtotime($day));
        }

        return $this->setExtraDays($diff);
    }

    public function calculateInstallmentCount(Money $nativeInstallmentAmount)
    {
        $maxYearlyInterest = $this->getNativeAmount()->multiply($this->getInterestRate() / 100);
        
        if ($this->isWeeklyInstallment()) {
            $maxInstallmentInterest = $maxYearlyInterest->divide(52);
        } else {
            $maxInstallmentInterest = $maxYearlyInterest->divide(12);
        }
        
        $maxNativeInstallmentAmount = $nativeInstallmentAmount->subtract($maxInstallmentInterest);
        
        $installmentCount = ceil($this->getNativeAmount()->getAmount() / $maxNativeInstallmentAmount->getAmount());

        return $this->setInstallmentCount($installmentCount);
    }

    public function calculateAmountRaised(Money $totalBidAmount)
    {
        if ($totalBidAmount->lessThan($this->getAmount())) {
            $percentAmountRaised = $totalBidAmount->divide($this->getAmount())->multiply(100)->round(2)->getAmount();
        } else {
            $percentAmountRaised = 100;
        }

        return $this->setAmountRaised($percentAmountRaised);
    }

    public function isWeeklyInstallment()
    {
        return $this->getInstallmentPeriod() == self::WEEKLY_INSTALLMENT;
    }
}
