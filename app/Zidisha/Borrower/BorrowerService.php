<?php
namespace Zidisha\Borrower;

use Zidisha\Borrower\Base\BorrowerQuery;
use Zidisha\Mail\BorrowerMailer;
use Zidisha\Upload\Upload;
use Zidisha\User\User;
use Zidisha\User\UserQuery;
use Zidisha\Vendor\Facebook\FacebookService;

class BorrowerService
{
    /**
     * @var \Zidisha\Vendor\Facebook\FacebookService
     */
    private $facebookService;
    /**
     * @var \Zidisha\User\UserQuery
     */
    private $userQuery;
    /**
     * @var \Zidisha\Mail\BorrowerMailer
     */
    private $borrowerMailer;

    public function __construct(FacebookService $facebookService, UserQuery $userQuery, BorrowerMailer $borrowerMailer)
    {
        $this->facebookService = $facebookService;
        $this->userQuery = $userQuery;
        $this->borrowerMailer = $borrowerMailer;
    }

    public function joinBorrower($data)
    {
        $volunteerMentor = VolunteerMentorQuery::create()
            ->findOneByBorrowerId($data['volunteerMentorId']);
        $referrer = BorrowerQuery::create()
            ->findOneById($data['members']);

        $user = new User();
        $user->setUsername($data['username']);
        $user->setEmail($data['email']);
        $user->setFacebookId($data['facebookId']);
        $user->setRole('borrower');

        $borrower = new Borrower();
        $borrower->setFirstName($data['firstName']);
        $borrower->setLastName($data['lastName']);
        $borrower->setCountryId($data['countryId']);
        $borrower->setVolunteerMentor($volunteerMentor);
        $borrower->setReferrer($referrer);
        $borrower->setUser($user);

        $profile = new Profile();
        $profile->setAddress($data['address']);
        $profile->setAddressInstructions($data['addressInstruction']);
        $profile->setCity($data['city']);
        $profile->setNationalIdNumber($data['nationalIdNumber']);
        $profile->setPhoneNumber($data['phoneNumber']);
        $profile->setAlternatePhoneNumber($data['alternatePhoneNumber']);
        $borrower->setProfile($profile);

        $communityLeader = new Contact();
        $communityLeader
            ->setType('communityLeader')
            ->setFirstName($data['communityLeader']['firstName'])
            ->setLastName($data['communityLeader']['lastName'])
            ->setPhoneNumber($data['communityLeader']['phoneNumber'])
            ->setDescription($data['communityLeader']['description']);
        $borrower->addContact($communityLeader);

        for ($i = 1; $i <= 3; $i++) {
            $familyMember = new Contact();
            $familyMember
                ->setType('familyMember')
                ->setFirstName($data['familyMember'][$i]['firstName'])
                ->setLastName($data['familyMember'][$i]['lastName'])
                ->setPhoneNumber($data['familyMember'][$i]['phoneNumber'])
                ->setDescription($data['familyMember'][$i]['description']);
            $borrower->addContact($familyMember);
        }

        for ($i = 1; $i <= 3; $i++) {
            $neighbor = new Contact();
            $neighbor
                ->setType('neighbor')
                ->setFirstName($data['neighbor'][$i]['firstName'])
                ->setLastName($data['neighbor'][$i]['lastName'])
                ->setPhoneNumber($data['neighbor'][$i]['phoneNumber'])
                ->setDescription($data['neighbor'][$i]['description']);
            $borrower->addContact($neighbor);
        }

        $borrower->save();

        $joinLog = new JoinLog();
        $joinLog
            ->setIpAddress($data['ipAddress'])
            ->setBorrower($borrower);
        $joinLog->save();

        $this->sendVerificationCode($borrower);

        return $borrower;
    }

    public function editBorrower(Borrower $borrower, $data, $files = [])
    {
        $borrower->setFirstName($data['firstName']);
        $borrower->setLastName($data['lastName']);
        $borrower->getUser()->setEmail($data['email']);
        $borrower->getUser()->setUsername($data['username']);
        $borrower->getProfile()->setAboutMe($data['aboutMe']);
        $borrower->getProfile()->setAboutBusiness($data['aboutBusiness']);

        if (!empty($data['password'])) {
            $borrower->getUser()->setPassword($data['password']);
        }

        if (\Input::hasFile('picture')) {
            $image = \Input::file('picture');

            $user = $borrower->getUser();

            if ($image) {
                $upload = Upload::createFromFile($image);
                $upload->setUser($user);

                $user->setProfilePicture($upload);
                //TODO: Test without user save
                $user->save();
            }
        }

        if ($files) {
            $user = $borrower->getUser();

            foreach ($files as $file) {
                $upload = Upload::createFromFile($file);
                $upload->setUser($user);
                $borrower->addUpload($upload);
            }
            $borrower->save();
        }

        $borrower->save();
    }

    public function deleteUpload(Borrower $borrower, Upload $upload)
    {
        $borrower->removeUpload($upload);
        $borrower->save();

        $upload->delete();
    }

    public function makeVolunteerMentor(Borrower $borrower)
    {
        $borrower->getUser()->setSubRole('volunteerMentorId');
        $borrower->save();
    }

    public function validateConnectingFacebookUser($facebookUser)
    {
        $checkUser = $this->userQuery
            ->filterByFacebookId($facebookUser['id'])
            ->_or()
            ->filterByEmail($facebookUser['email'])
            ->findOne();

        $errors = array();
        if ($checkUser) {
            if ($checkUser->getFacebookId() == $facebookUser['id']) {
                $errors[] = \Lang::get('borrower-registration.account-already-linked');
            } else {
                $errors[] = \Lang::get('borrower-registration.email-address-already-linked');
            }
        }

        if (!$this->facebookService->isAccountOldEnough()) {
            $errors[] = \Lang::get('borrower-registration.account-not-old');
        }

        if (!$this->facebookService->hasEnoughFriends()) {
            $errors[] = \Lang::get('borrower-registration.does-not-have-enough-friends');
        }

        if (!$facebookUser['verified']) {
            $errors[] = \Lang::get('borrower-registration.facebook-email-not-verified');
        }

        return $errors;
    }

    protected function createVerificationToken()
    {
        return md5(uniqid(rand(), true));
    }

    public function sendVerificationCode(Borrower $borrower)
    {
        $hashCode = $this->createVerificationToken();

        $joinLog = $borrower->getJoinLog();
        $joinLog
            ->setVerificationCode($hashCode);
        $joinLog->save();

        $this->borrowerMailer->sendVerificationMail($borrower, $hashCode);
    }

    public function saveBorrowerGuest($formData, $sessionData)
    {
        $email = array_get($formData, 'email');

        $code = array_get($sessionData, 'resumeCode');
        if ($code) {
            $borrowerGuest = \Zidisha\Borrower\BorrowerGuestQuery::create()
                ->findOneByResumecode($code);
        } else {
            $code = md5(uniqid(rand(), true));

            $borrowerGuest = new BorrowerGuest();
        }

        $formData = serialize($formData);
        $sessionData = serialize($sessionData);

        $borrowerGuest
            ->setEmail($email)
            ->setResumecode($code)
            ->setSession($sessionData)
            ->setForm($formData);

        $borrowerGuest->save();

        $this->borrowerMailer->sendFormResumeLaterMail($email, $code);

        \Session::forget('BorrowerJoin');

        \Flash::info(\Lang::get('borrower.save-later.information-is-saved'));
        \Flash::info(
            \Lang::get(
                'borrower.save-later.application-resume-link' . ' ' . route('borrower:resumeApplication', $code)
            )
        );
        \Flash::info(\Lang::get('borrower.save-later.application-resume-code' . ' ' . $code));
        return \Redirect::action('BorrowerJoinController@getCountry');
    }
}
