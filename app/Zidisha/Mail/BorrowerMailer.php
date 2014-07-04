<?php
namespace Zidisha\Mail;


use Zidisha\Borrower\Borrower;
use Zidisha\Borrower\FeedbackMessage;

class BorrowerMailer{

    /**
     * @var Mailer
     */
    private $mailer;

    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    public function sendVerificationMail(Borrower $borrower)
    {
        $data = [
            'hashCode' => $borrower->getJoinLog()->getVerificationCode(),
            'to' => $borrower->getUser()->getEmail(),
            'from' => 'service@zidisha.org',
            'subject' => 'Zidisha Borrower Account Verification'
        ];

        $this->mailer->send(
            'emails.borrower.verification',
            $data
        );
    }

    public function sendBorrowerJoinedConfirmationMail(Borrower $borrower)
    {
        $data = [
            'borrower' => $borrower,
            'to'        => $borrower->getUser()->getEmail(),
            'from'      => 'noreply@zidisha.org',
            'subject'   => \Lang::get('borrowerJoin.emails.subject.confirmation')
        ];

        $this->mailer->send('emails.borrower.join.confirmation', $data);
    }

    public function sendFormResumeLaterMail($email, $resumeCode)
    {
        $data = [
            'resumeCode' => $resumeCode,
            'to' => $email,
            'from' => 'service@zidisha.org',
            'subject' => 'Zidisha Borrower Account Verification'
        ];

        $this->mailer->send(
            'emails.borrower.resumeLater',
            $data
        );
    }

    public function sendBorrowerJoinedVolunteerMentorConfirmationMail(Borrower $borrower)
    {
        $subject = \Lang::get('borrowerJoin.emails.subject.volunteer-mentor-confirmation', ['name' => $borrower->getName()]);
        $data = [
            'borrower' => $borrower,
            'to'        => $borrower->getVolunteerMentor()->getBorrowerVolunteer()->getUser()->getEmail(),
            'from'      => 'service@zidisha.org',
            'subject'   => $subject,
        ];

        $this->mailer->send('emails.borrower.join.volunteer-mentor-confirmation', $data);
    }

    public function sendLoanFeedbackMail(FeedbackMessage $feedbackMessage)
    {
        $data = [
            'feedback' => $feedbackMessage->getMessage(),
            'to'    => $feedbackMessage->getBorrowerEmail(),
            'from'  => $feedbackMessage->getReplyTo(),
            'subject' => $feedbackMessage->getSubject()
        ];

        $this->mailer->send('emails.borrower.feature-feedback', $data);

        if($feedbackMessage->getCc() != null){
            $emails = explode(",", $feedbackMessage->getCc());
            foreach($emails as $email)
            {
                $email = trim($email);
                $data['to'] = $email;
                $this->mailer->send('emails.borrower.feature-feedback', $data);
            }
        }
    }
}