<?php
namespace Zidisha\Mail;


use Symfony\Component\Translation\Tests\String;
use Zidisha\Lender\Invite;
use Zidisha\Lender\Lender;
use Zidisha\Loan\Bid;

/**
 * Class LenderMailer
 * @package Zidisha\Mail
 */
class LenderMailer
{

    /**
     * @var mailer
     */
    private $mailer;

    /**
     * @param Mailer $mailer
     */
    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * @param Bid $bid
     */
    public function sendPlaceBidMail(Bid $bid)
    {
        $email = $bid->getLender()->getUser()->getEmail();

        $this->mailer->send(
            'emails.loan.placed-first-bid',
            [
                'to' => $email,
                'from' => 'service@zidisha.com',
                'subject' => 'Congratulations you have made your first Bid on Zidisha.'
            ]
        );
    }


    /**
     * @param Bid $bid
     */
    public function loanCompletionMail(Bid $bid)
    {
        $email = $bid->getLender()->getUser()->getEmail();

        $this->mailer->send(
            'emails.loan.loan-completed',
            [
                'to' => $email,
                'from' => 'service@zidisha.com',
                'subject' => 'The loan is fully funded.'
            ]
        );
    }

    /**
     * @param Bid $bid
     */
    public function bidPlaceMail(Bid $bid)
    {
        $email = $bid->getLender()->getUser()->getEmail();

        $this->mailer->send(
            'emails.loan.bid-placed',
            [
                'to' => $email,
                'from' => 'service@zidisha.com',
                'subject' => 'Congratulations your Bid is successfully placed on Zidisha.'
            ]
        );
    }

    public function sendLenderInvite(Lender $lender, Invite $lender_invite, $subject, $custom_message)
    {
        $email = $lender_invite->getEmail();
        //TODO send invite email
    }

    public function sendLenderInviteCredit(Invite $invite)
    {
        //TODO
    }
}
