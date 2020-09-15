<?php

namespace App\Controller;

use App\Form\ProfileType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class ProfileController extends AbstractController
{
    /**
     * @Route("/profil", name="profile_edit")
     * @IsGranted("ROLE_USER")
     */
    public function index(Request $request, UserPasswordEncoderInterface $encoder)
    {
        $user = $this->getUser();
        
        $profileForm = $this->createForm(ProfileType::class, $user);

        $profileForm->handleRequest($request);

        if($profileForm->isSubmitted() && $profileForm->isValid()){
            $oldPassword = $profileForm->get('oldPassword')->getData();
            $encodedPass = $encoder->isPasswordValid($user, $oldPassword);
            if($encodedPass == true) {
                
                $em = $this->getDoctrine()->getManager();
                $em->persist($user);
                $em->flush();
                $this->addFlash('success', 'Votre profil a bien été modifié.');
                return $this->redirect($this->generateUrl('profile_edit'));
            }

            else{
                $this->addFlash('error', 'Votre mot de passe est incorrect.');
                return $this->redirect($this->generateUrl('profile_edit'));
            }
             

        }

        return $this->render('profile/edit.html.twig', [
            'profileForm' => $profileForm->createView(),
        ]);
    }
}
