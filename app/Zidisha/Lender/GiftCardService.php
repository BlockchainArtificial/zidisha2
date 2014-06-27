<?php

namespace Zidisha\Lender;

use Propel\Runtime\Propel;
use Whoops\Example\Exception;
use Zidisha\Balance\Map\TransactionTableMap;
use Zidisha\Balance\TransactionService;
use Zidisha\Currency\Money;
use Faker\Factory as Faker;

class GiftCardService
{

    private $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    public function validateCode($redemptionCode)
    {
        $count = GiftCardQuery::create()
            ->filterByCardCode($redemptionCode)
            ->count();
        if ($count > 1) {
            return 'comments.flash.duplicate-code';
        }
        $giftCard = GiftCardQuery::create()
            ->filterByCardCode($redemptionCode)
            ->findOne();
        if (!$giftCard) {
            return 'comments.flash.invalid-code';
        }

        if ($giftCard->getStatus() == 1) {
            $giftCard = GiftCardQuery::create()
                ->filterByCardCode($redemptionCode)
                ->findOne();
            if ($giftCard->getClaimed() == 1) {
                return 'comments.flash.redeemed-code';
            } else {
                $currentDate = new \DateTime();
                if ($giftCard->getExpireDate() < $currentDate) {
                    return 'comments.flash.expired-code';
                }
            }
        } elseif ($giftCard->getStatus() == 0) {
            return 'comments.flash.invalid-code';
        }
    }

    public function addGiftCard(Lender $lender, $data)
    {
        $data += [
            'recipientName' => null,
            'fromName' => null,
            'message' => null,
            'confirmationEmail' => null,
        ];

        $amount = Money::create($data['amount'], 'USD');
        $faker = Faker::create();

        $giftCard = new GiftCard();
        $giftCard
            ->setLender($lender)
            ->setTemplate($data['template'])
            ->setOrderType($data['orderType'])
            ->setCardAmount($amount)
            ->setRecipientEmail($data['recipientEmail'])
            ->setRecipientName($data['recipientName'])
            ->setFromName($data['fromName'])
            ->setMessage($data['message'])
            ->setDate(new \DateTime())
            ->setExpireDate(strtotime('+1 year'))
            ->setCardCode($faker->creditCardNumber)
            ->setConfirmationEmail($data['confirmationEmail']);
        $giftCard->save();

        // Todo send email

        return $giftCard;
    }

    public function redeemGiftCard(Lender $recipient, $redemptionCode)
    {
        $giftCard = GiftCardQuery::create()
            ->findOneByCardCode($redemptionCode);

        $con = Propel::getWriteConnection(TransactionTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $giftCard
                ->setClaimed(1)
                ->setRecipient($recipient);

            $giftCard->save($con);

            $this->transactionService->addRedeemGiftCardTransaction($con, $giftCard);
            $con->commit();
        } catch (Exception $e) {
            $con->rollBack();
            throw $e;
        }

        return $giftCard;
    }

}
