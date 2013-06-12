<?php

namespace Fast\SisdikBundle\Controller;
use Symfony\Component\Form\FormError;
use Fast\SisdikBundle\Entity\Tahun;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\Response;
use Fast\SisdikBundle\Entity\User;
use Doctrine\DBAL\DBALException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Fast\SisdikBundle\Entity\PanitiaPendaftaran;
use Fast\SisdikBundle\Form\PanitiaPendaftaranType;
use Fast\SisdikBundle\Entity\Personil;
use Fast\SisdikBundle\Form\SimpleTahunSearchType;
use Fast\SisdikBundle\Entity\Sekolah;
use JMS\SecurityExtraBundle\Annotation\PreAuthorize;

/**
 * PanitiaPendaftaran controller.
 *
 * @Route("/regcommittee")
 * @PreAuthorize("hasAnyRole('ROLE_ADMIN', 'ROLE_KEPALA_SEKOLAH', 'ROLE_WAKIL_KEPALA_SEKOLAH')")
 */
class PanitiaPendaftaranController extends Controller
{
    /**
     * Lists all PanitiaPendaftaran entities.
     *
     * @Route("/", name="regcommittee")
     * @Template()
     */
    public function indexAction() {
        $sekolah = $this->isRegisteredToSchool();
        $this->setCurrentMenu();

        $em = $this->getDoctrine()->getManager();

        $searchform = $this->createForm(new SimpleTahunSearchType($this->container));

        $querybuilder = $em->createQueryBuilder()->select('t')
                ->from('FastSisdikBundle:PanitiaPendaftaran', 't')->leftJoin('t.tahun', 't2')
                ->where('t.sekolah = :sekolah')->orderBy('t2.tahun', 'DESC');
        $querybuilder->setParameter('sekolah', $sekolah->getId());

        $searchform->submit($this->getRequest());
        if ($searchform->isValid()) {
            $searchdata = $searchform->getData();

            if ($searchdata['tahun'] != '') {
                $querybuilder->andWhere('t2.id = :tahun');
                $querybuilder->setParameter('tahun', $searchdata['tahun']->getId());
            }
        }

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate($querybuilder, $this->getRequest()->query->get('page', 1));

        return array(
            'pagination' => $pagination, 'searchform' => $searchform->createView()
        );
    }

    /**
     * Finds and displays a PanitiaPendaftaran entity.
     *
     * @Route("/{id}/show", name="regcommittee_show")
     * @Template()
     */
    public function showAction($id) {
        $this->isRegisteredToSchool();
        $this->setCurrentMenu();

        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('FastSisdikBundle:PanitiaPendaftaran')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Entity PanitiaPendaftaran tak ditemukan.');
        }

        $deleteForm = $this->createDeleteForm($id);

        return array(
            'entity' => $entity, 'delete_form' => $deleteForm->createView(),
        );
    }

    /**
     * Activate a panitia entity, and deactivate the rests.
     *
     * @Route("/{id}/activate", name="regcommittee_activate")
     */
    public function activateAction($id) {
        $sekolah = $this->isRegisteredToSchool();
        $this->setCurrentMenu();

        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('FastSisdikBundle:PanitiaPendaftaran')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Entity PanitiaPendaftaran tak ditemukan.');
        }

        $results = $em->getRepository('FastSisdikBundle:Tahun')
                ->findBy(array(
                    'sekolah' => $sekolah->getId()
                ));
        $daftarTahun = array();
        foreach ($results as $tahun) {
            if (is_object($tahun) && $tahun instanceof Tahun) {
                $daftarTahun[] = $tahun->getId();
            }
        }

        $query = $em->createQueryBuilder()->update('FastSisdikBundle:PanitiaPendaftaran', 't')
                ->set('t.aktif', '0')->where('t.tahun IN (?1)')->setParameter(1, $daftarTahun)->getQuery();
        $query->execute();

        $entity->setAktif(1);
        $entity->setDaftarPersonil($entity->getDaftarPersonil());
        $em->persist($entity);
        $em->flush();

        return $this
                ->redirect(
                        $this
                                ->generateUrl('regcommittee',
                                        array(
                                            'page' => $this->getRequest()->get('page')
                                        )));
    }

    /**
     * Displays a form to create a new PanitiaPendaftaran entity.
     *
     * @Route("/new", name="regcommittee_new")
     * @Template()
     */
    public function newAction() {
        $this->isRegisteredToSchool();
        $this->setCurrentMenu();

        $entity = new PanitiaPendaftaran();

        $form = $this->createForm(new PanitiaPendaftaranType($this->container), $entity);

        return array(
            'entity' => $entity, 'form' => $form->createView(),
        );
    }

    /**
     * Creates a new PanitiaPendaftaran entity.
     *
     * @Route("/create", name="regcommittee_create")
     * @Method("POST")
     * @Template("FastSisdikBundle:PanitiaPendaftaran:new.html.twig")
     */
    public function createAction(Request $request) {
        $this->isRegisteredToSchool();
        $this->setCurrentMenu();

        $entity = new PanitiaPendaftaran();
        $form = $this->createForm(new PanitiaPendaftaranType($this->container), $entity);
        $form->submit($request);

        // prevent or remove empty personil to be inserted to database.
        $formdata = $form->getData();
        if (count($formdata->getDaftarPersonil()) > 1) {
            foreach ($formdata->getDaftarPersonil() as $personil) {
                if ($personil->getId() === null) {
                    $formdata->getDaftarPersonil()->removeElement($personil);
                }
            }
        } else {
            foreach ($formdata->getDaftarPersonil() as $personil) {
                if ($personil->getId() === null) {
                    $message = $this->get('translator')->trans('alert.regcommittee.notempty');
                    $form->get('daftarPersonil')->addError(new FormError($message));
                }
            }
        }

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();

            try {
                $em->persist($entity);

                // give user the necessary role
                $userManager = $this->container->get('fos_user.user_manager');
                $daftarPersonil = $entity->getDaftarPersonil();
                if ($daftarPersonil instanceof ArrayCollection) {
                    foreach ($daftarPersonil as $personil) {
                        if ($personil instanceof Personil) {
                            if ($personil->getId() !== NULL) {
                                $user = $userManager
                                        ->findUserBy(
                                                array(
                                                    'id' => $personil->getId()
                                                ));

                                $user->addRole('ROLE_PANITIA_PSB');
                                $userManager->updateUser($user);
                            }
                        }
                    }
                }

                $user = $userManager
                        ->findUserBy(
                                array(
                                    'id' => $entity->getKetuaPanitia()->getId()
                                ));
                $user->addRole('ROLE_KETUA_PANITIA_PSB');
                $userManager->updateUser($user);

                $em->flush();

                $this->get('session')->getFlashBag()
                        ->add('success',
                                $this->get('translator')
                                        ->trans('flash.registration.committee.inserted',
                                                array(
                                                    '%year%' => $entity->getTahun()->getTahun()
                                                )));
            } catch (DBALException $e) {
                $message = $this->get('translator')
                        ->trans('exception.unique.registration.committee',
                                array(
                                    '%year%' => $entity->getTahun()->getTahun()
                                ));
                throw new DBALException($message);
            }

            return $this
                    ->redirect(
                            $this
                                    ->generateUrl('regcommittee_show',
                                            array(
                                                'id' => $entity->getId()
                                            )));
        }

        return array(
            'entity' => $entity, 'form' => $form->createView(),
        );
    }

    /**
     * Displays a form to edit an existing PanitiaPendaftaran entity.
     *
     * @Route("/{id}/edit", name="regcommittee_edit")
     * @Template()
     */
    public function editAction($id) {
        $this->isRegisteredToSchool();
        $this->setCurrentMenu();

        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('FastSisdikBundle:PanitiaPendaftaran')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Entity PanitiaPendaftaran tak ditemukan.');
        }

        $editForm = $this->createForm(new PanitiaPendaftaranType($this->container), $entity);
        $deleteForm = $this->createDeleteForm($id);

        return array(
                'entity' => $entity, 'edit_form' => $editForm->createView(),
                'delete_form' => $deleteForm->createView(),
        );
    }

    /**
     * Edits an existing PanitiaPendaftaran entity.
     *
     * @Route("/{id}/update", name="regcommittee_update")
     * @Method("POST")
     * @Template("FastSisdikBundle:PanitiaPendaftaran:edit.html.twig")
     */
    public function updateAction(Request $request, $id) {
        $this->isRegisteredToSchool();
        $this->setCurrentMenu();

        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('FastSisdikBundle:PanitiaPendaftaran')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Entity PanitiaPendaftaran tak ditemukan.');
        }

        $deleteForm = $this->createDeleteForm($id);

        $entity->setPanitia(''); // prevent form generation read from database

        $editForm = $this->createForm(new PanitiaPendaftaranType($this->container), $entity);
        $editForm->submit($request);

        // prevent or remove empty personil to be inserted to database.
        $formdata = $editForm->getData();
        if (count($formdata->getDaftarPersonil()) > 1) {
            foreach ($formdata->getDaftarPersonil() as $personil) {
                if ($personil->getId() === null) {
                    $formdata->getDaftarPersonil()->removeElement($personil);
                }
            }
        } else {
            foreach ($formdata->getDaftarPersonil() as $personil) {
                if ($personil->getId() === null) {
                    $message = $this->get('translator')->trans('alert.regcommittee.notempty');
                    $editForm->get('daftarPersonil')->addError(new FormError($message));
                }
            }
        }

        if ($editForm->isValid()) {

            try {
                $em->persist($entity);

                // give user the necessary role
                $userManager = $this->container->get('fos_user.user_manager');
                $daftarPersonil = $entity->getDaftarPersonil();
                if ($daftarPersonil instanceof ArrayCollection) {
                    foreach ($daftarPersonil as $personil) {
                        if ($personil instanceof Personil) {
                            if ($personil->getId() !== NULL) {
                                $user = $userManager
                                        ->findUserBy(
                                                array(
                                                    'id' => $personil->getId()
                                                ));

                                if ($user instanceof User) {
                                    $user->addRole('ROLE_PANITIA_PSB');
                                    $userManager->updateUser($user);
                                }
                            }
                        }
                    }
                }

                $user = $userManager
                        ->findUserBy(
                                array(
                                    'id' => $entity->getKetuaPanitia()->getId()
                                ));
                $user->addRole('ROLE_KETUA_PANITIA_PSB');
                $userManager->updateUser($user);

                $em->flush();

                $this->get('session')->getFlashBag()
                        ->add('success',
                                $this->get('translator')
                                        ->trans('flash.registration.committee.updated',
                                                array(
                                                    '%year%' => $entity->getTahun()->getTahun()
                                                )));

            } catch (DBALException $e) {
                $message = $this->get('translator')
                        ->trans('exception.unique.registration.committee',
                                array(
                                    '%year%' => $entity->getTahun()->getTahun()
                                ));
                throw new DBALException($message);
            }

            return $this
                    ->redirect(
                            $this
                                    ->generateUrl('regcommittee_edit',
                                            array(
                                                'id' => $id, 'page' => $this->getRequest()->get('page')
                                            )));
        }

        return array(
                'entity' => $entity, 'edit_form' => $editForm->createView(),
                'delete_form' => $deleteForm->createView(),
        );
    }

    /**
     * Deletes a PanitiaPendaftaran entity.
     *
     * @Route("/{id}/delete", name="regcommittee_delete")
     * @Method("POST")
     */
    public function deleteAction(Request $request, $id) {
        $this->isRegisteredToSchool();

        $form = $this->createDeleteForm($id);
        $form->submit($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $entity = $em->getRepository('FastSisdikBundle:PanitiaPendaftaran')->find($id);

            if (!$entity) {
                throw $this->createNotFoundException('Entity PanitiaPendaftaran tak ditemukan.');
            }

            $em->remove($entity);
            $em->flush();

            $this->get('session')->getFlashBag()
                    ->add('success',
                            $this->get('translator')
                                    ->trans('flash.registration.committee.deleted',
                                            array(
                                                '%year%' => $entity->getTahun()->getTahun()
                                            )));
        } else {
            $this->get('session')->getFlashBag()
                    ->add('error',
                            $this->get('translator')
                                    ->trans('flash.registration.committee.fail.delete',
                                            array(
                                                '%year%' => $entity->getTahun()->getTahun()
                                            )));
        }

        return $this->redirect($this->generateUrl('regcommittee'));
    }

    /**
     * Finds a name of a username
     *
     * @Route("/name/{id}", name="regcommittee_getname")
     */
    public function getNameAction($id) {
        $this->isRegisteredToSchool();

        $em = $this->getDoctrine()->getManager();
        $entity = $em->getRepository('FastSisdikBundle:User')->find($id);

        if ($entity instanceof User) {
            $name = $entity->getName();
        } else {
            $name = $this->get('translator')->trans('label.username.undefined');
        }

        return new Response($name);
    }

    /**
     * Get username through autocomplete box
     *
     * @param Request $request
     * @Route("/ajax/ambilusername", name="regcommittee_ajax_get_username")
     */
    public function ajaxGetUsername(Request $request) {
        $sekolah = $this->isRegisteredToSchool();

        $em = $this->getDoctrine()->getManager();

        $filter = $this->getRequest()->query->get('filter');
        $id = $this->getRequest()->query->get('id');

        $querybuilder = $em->createQueryBuilder()->select('t')->from('FastSisdikBundle:User', 't')
                ->where('t.sekolah IS NOT NULL')->andWhere('t.sekolah = :sekolah')
                ->andWhere('t.siswa IS NULL')->orderBy('t.name', 'ASC')
                ->setParameter('sekolah', $sekolah->getId());

        if ($id != '') {
            $querybuilder->andWhere('t.id = :id');
            $querybuilder->setParameter('id', $id);
        } else {
            $querybuilder->andWhere('t.username LIKE ?1 OR t.name LIKE ?2');
            $querybuilder->setParameter(1, "%$filter%");
            $querybuilder->setParameter(2, "%$filter%");
        }

        $results = $querybuilder->getQuery()->getResult();

        $retval = array();
        foreach ($results as $result) {
            if ($result instanceof User) {
                $retval[] = array(
                        'source' => 'user', // user property of Personil
                        'target' => 'id', // id property of Personil
                        'id' => $result->getId(),
                        'label' => $result->getName() . " ({$result->getUsername()})",
                        'value' => $result->getName(), // . $result->getId() . ':' . $result->getUsername(),
                );
            }
        }

        if (count($retval) == 0) {
            $label = $this->container->get('translator')->trans("label.username.undefined");
            $retval[] = array(
                'source' => 'user', 'target' => 'id', 'id' => $id, 'label' => $label, 'value' => $label
            );
        }

        return new Response(json_encode($retval), 200,
                array(
                    'Content-Type' => 'application/json'
                ));
    }

    private function createDeleteForm($id) {
        return $this->createFormBuilder(array(
                    'id' => $id
                ))->add('id', 'hidden')->getForm();
    }

    private function setCurrentMenu() {
        $menu = $this->container->get('fast_sisdik.menu.main');
        $menu['headings.pendaftaran']['links.regcommittee']->setCurrent(true);
    }

    private function isRegisteredToSchool() {
        $user = $this->getUser();
        $sekolah = $user->getSekolah();

        if (is_object($sekolah) && $sekolah instanceof Sekolah) {
            return $sekolah;
        } elseif ($this->container->get('security.context')->isGranted('ROLE_SUPER_ADMIN')) {
            throw new AccessDeniedException($this->get('translator')->trans('exception.useadmin'));
        } else {
            throw new AccessDeniedException($this->get('translator')->trans('exception.registertoschool'));
        }
    }
}
