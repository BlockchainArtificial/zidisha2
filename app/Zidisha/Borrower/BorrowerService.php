<?php
namespace Zidisha\Borrower;

use Illuminate\Support\Facades\Input;
use Zidisha\Upload\Upload;

class BorrowerService
{
    public function joinBorrower($data)
    {
        $user = new \Zidisha\User\User();
        $user->setUsername($data['username']);
        $user->setEmail($data['email']);
        $user->setFacebookId($data['facebook_id']);
        $user->setRole('borrower');

        $borrower = new Borrower();
        $borrower->setFirstName($data['first_name']);
        $borrower->setLastName($data['last_name']);
        $borrower->setCountryId($data['country']);
        $borrower->setUser($user);

        $profile = new Profile();
        $profile->setAddress($data['address']);
        $profile->setAddressInstruction($data['address_instruction']);
        $profile->setVillage($data['village']);
        $profile->setNationalIdNumber($data['national_id_number']);
        $profile->setPhoneNumber($data['phone_number']);
        $profile->setAlternatePhoneNumber($data['alternate_phone_number']);
        $borrower->setProfile($profile);

        $borrower->save();

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

        if(Input::hasFile('picture'))
        {
            $image = Input::file('picture');

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
}
