<?php

namespace Zidisha\Balance;

use SupremeNewMedia\Finance\Core\Currency;
use SupremeNewMedia\Finance\Core\Money;
use Zidisha\Balance\Base\TransactionQuery as BaseTransactionQuery;


/**
 * Skeleton subclass for performing query and update operations on the 'transactions' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class TransactionQuery extends BaseTransactionQuery
{
    public function getTotalBalance()
    {
        $total = $this
            ->select(array('total'))
            ->withColumn('SUM(amount)', 'total')
            ->findOne();

        return Money::valueOf($total, Currency::valueOf('USD'));
    }

} // TransactionQuery
